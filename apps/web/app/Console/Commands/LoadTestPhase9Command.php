<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Phase 9 Sprint 4 load-testing tool (ADR-027 Decision 3/6) — drives real
 * HTTP traffic against the real nginx/php-fpm/Postgres/Redis/Horizon/Reverb
 * stack to gather evidence for empirical Horizon capacity tuning. Never
 * bypasses HTTP: bid placement is the one surface Decision 0 built
 * specifically for this purpose, so this tool exercises it exactly as a
 * real client would (real session cookies, real CSRF tokens), not via
 * direct service calls.
 *
 * Retained as permanent ops tooling, not a throwaway script — Decision 3's
 * targets are explicitly provisional and expected to be re-run once real
 * telemetry exists.
 */
final class LoadTestPhase9Command extends Command
{
    protected $signature = 'loadtest:phase9
        {--base-url=http://nginx : Base URL to target (internal Docker hostname by default)}
        {--hot-bidders=25 : Peak concurrent bidders on the hot auction (Decision 3)}
        {--background-users=225 : Additional users generating background traffic (25 + this = 250, Decision 3)}
        {--background-auctions=99 : Additional concurrent active auctions besides the hot one (1 + this = 100, Decision 3)}
        {--duration=60 : Sustained burst duration in seconds (Decision 3)}
        {--rate=10 : Target bid submissions per second on the hot auction (Decision 3)}';

    protected $description = 'Phase 9 Sprint 4: load-test bid placement under concurrency against real HTTP/Horizon/Reverb';

    /** @var array<int, array{endpoint: string, status: int, ms: float}> */
    private array $results = [];

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $hotBidderCount = (int) $this->option('hot-bidders');
        $backgroundUserCount = (int) $this->option('background-users');
        $backgroundAuctionCount = (int) $this->option('background-auctions');
        $durationSeconds = (int) $this->option('duration');
        $ratePerSecond = (int) $this->option('rate');

        $this->info('Seeding fixtures...');
        $fixtures = $this->seed($baseUrl, $hotBidderCount, $backgroundUserCount, $backgroundAuctionCount);

        $this->info(sprintf(
            'Logging in %d simulated users (this takes a while)...',
            count($fixtures['hotBidders']) + count($fixtures['backgroundUsers']),
        ));
        $hotClients = $this->loginAll($baseUrl, $fixtures['hotBidders']);
        $backgroundClients = $this->loginAll($baseUrl, $fixtures['backgroundUsers']);

        $failedJobsBefore = (int) DB::table('failed_jobs')->count();
        $queueDepthBefore = $this->queueDepth();

        $this->info(sprintf(
            'Running %ds burst: %d req/sec on the hot auction + background traffic across %d auctions...',
            $durationSeconds,
            $ratePerSecond,
            count($fixtures['backgroundAuctionIds']),
        ));
        $startedAt = microtime(true);
        $this->runBurst($baseUrl, $fixtures, $hotClients, $backgroundClients, $durationSeconds, $ratePerSecond);
        $wallClockSeconds = microtime(true) - $startedAt;

        $this->info('Draining queued jobs (10s)...');
        sleep(10);

        $failedJobsAfter = (int) DB::table('failed_jobs')->count();
        $queueDepthAfter = $this->queueDepth();

        $this->printReport($wallClockSeconds, $failedJobsBefore, $failedJobsAfter, $queueDepthBefore, $queueDepthAfter);

        return self::SUCCESS;
    }

    /**
     * @return array{hotAuctionId: string, hotBidders: list<User>, backgroundAuctionIds: list<string>, backgroundUsers: list<User>}
     */
    private function seed(string $baseUrl, int $hotBidderCount, int $backgroundUserCount, int $backgroundAuctionCount): array
    {
        $hotBidders = User::factory()->count($hotBidderCount)->create()->all();
        $backgroundUsers = User::factory()->count($backgroundUserCount)->create()->all();

        $bar = $this->output->createProgressBar(1 + $backgroundAuctionCount);
        $hotAuctionId = $this->createOpenAuction($baseUrl);
        $bar->advance();
        $backgroundAuctionIds = [];

        for ($i = 0; $i < $backgroundAuctionCount; $i++) {
            $backgroundAuctionIds[] = $this->createOpenAuction($baseUrl);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        return [
            'hotAuctionId' => $hotAuctionId,
            'hotBidders' => $hotBidders,
            'backgroundAuctionIds' => $backgroundAuctionIds,
            'backgroundUsers' => $backgroundUsers,
        ];
    }

    /**
     * Opens a real, checker-safe auction through the real HTTP presence
     * flow — mirroring PlaceBidTest's own createOpenAuctionForBidTest()
     * recipe exactly. A directly-inserted Auction row with a fabricated
     * presence_session_id is NOT safe to bid against: the real
     * EloquentAuctionGateway's LiveProximityChecker cancels it the moment
     * a bid is attempted, since no real PresenceSession backs it — this
     * was discovered the hard way during this sprint's own dry run.
     */
    private function createOpenAuction(string $baseUrl): string
    {
        $seller = User::factory()->create();
        $queueId = (string) Str::uuid();

        QueueModel::query()->create([
            'id' => $queueId,
            'category' => 'concert',
            'jurisdiction_country' => 'US',
            'center_latitude' => 32.7157,
            'center_longitude' => -117.1611,
            'radius_meters' => 200,
            'authorship' => 'admin_curated',
            'organizer_reference' => 'loadtest-'.$queueId,
            'status' => 'published',
        ]);

        $jar = new CookieJar;
        $client = new Client(['base_uri' => $baseUrl, 'cookies' => $jar, 'timeout' => 30, 'http_errors' => false]);
        $client->get('/login');
        $client->post('/login', [
            'headers' => ['X-XSRF-TOKEN' => $this->xsrfTokenFrom($jar, $baseUrl), 'Accept' => 'application/json'],
            'form_params' => ['email' => $seller->email, 'password' => 'password'],
        ]);

        $sessionResponse = $client->post('/presence-sessions', [
            'headers' => ['X-XSRF-TOKEN' => $this->xsrfTokenFrom($jar, $baseUrl), 'Accept' => 'application/json'],
            'json' => ['queue_id' => $queueId],
        ]);
        $sessionId = json_decode((string) $sessionResponse->getBody(), true)['data']['id'];

        $client->post("/presence-sessions/{$sessionId}/gps-pings", [
            'headers' => ['X-XSRF-TOKEN' => $this->xsrfTokenFrom($jar, $baseUrl), 'Accept' => 'application/json'],
            'json' => ['latitude' => 32.7157, 'longitude' => -117.1611, 'accuracy_meters' => 5.0],
        ]);

        $client->post("/presence-sessions/{$sessionId}/evidence-photos", [
            'headers' => ['X-XSRF-TOKEN' => $this->xsrfTokenFrom($jar, $baseUrl)],
            'multipart' => [[
                'name' => 'photo',
                'contents' => $this->fakeJpegBytes(),
                'filename' => 'evidence.jpg',
                'headers' => ['Content-Type' => 'image/jpeg'],
            ]],
        ]);

        $auctionId = (string) Str::uuid();
        app(AuctionService::class)->open(
            $auctionId,
            $queueId,
            (string) $seller->id,
            $sessionId,
            new Money(1000, new Currency('USD')),
        );

        return $auctionId;
    }

    private function fakeJpegBytes(): string
    {
        $image = imagecreatetruecolor(100, 100);
        imagefilledrectangle($image, 0, 0, 100, 100, imagecolorallocate($image, 200, 200, 200));
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * @param  list<User>  $users
     * @return list<Client>
     */
    private function loginAll(string $baseUrl, array $users): array
    {
        $clients = [];

        foreach ($users as $user) {
            $jar = new CookieJar;
            $client = new Client(['base_uri' => $baseUrl, 'cookies' => $jar, 'timeout' => 10, 'http_errors' => false]);

            $client->get('/login');
            $xsrfToken = $this->xsrfTokenFrom($jar, $baseUrl);

            $client->post('/login', [
                'headers' => ['X-XSRF-TOKEN' => $xsrfToken, 'Accept' => 'application/json'],
                'form_params' => ['email' => $user->email, 'password' => 'password'],
            ]);

            $clients[] = $client;
        }

        return $clients;
    }

    private function xsrfTokenFrom(CookieJar $jar, string $baseUrl): string
    {
        foreach ($jar->toArray() as $cookie) {
            if ($cookie['Name'] === 'XSRF-TOKEN') {
                return rawurldecode($cookie['Value']);
            }
        }

        return '';
    }

    /**
     * @param  array{hotAuctionId: string, hotBidders: list<User>, backgroundAuctionIds: list<string>, backgroundUsers: list<User>}  $fixtures
     * @param  list<Client>  $hotClients
     * @param  list<Client>  $backgroundClients
     */
    private function runBurst(
        string $baseUrl,
        array $fixtures,
        array $hotClients,
        array $backgroundClients,
        int $durationSeconds,
        int $ratePerSecond,
    ): void {
        $hotAuctionId = $fixtures['hotAuctionId'];
        $backgroundAuctionIds = $fixtures['backgroundAuctionIds'];
        $basePriceMinorUnits = 1000;
        $bar = $this->output->createProgressBar($durationSeconds);

        for ($second = 0; $second < $durationSeconds; $second++) {
            $iterationStart = microtime(true);
            $requests = [];

            // Hot-auction requests: Decision 3's own named scenario.
            for ($i = 0; $i < $ratePerSecond; $i++) {
                $client = $hotClients[random_int(0, count($hotClients) - 1)];
                $amount = $basePriceMinorUnits + ($second * $ratePerSecond + $i + 1) * 10;
                $requests[] = $this->bidRequest($client, $baseUrl, $hotAuctionId, $amount);
            }

            // Background traffic: simulates the other 99 concurrent
            // active auctions and the remaining users toward the 250
            // peak-concurrent-authenticated-users target.
            $backgroundBurstSize = min(5, count($backgroundClients));
            for ($i = 0; $i < $backgroundBurstSize; $i++) {
                $client = $backgroundClients[random_int(0, count($backgroundClients) - 1)];
                $auctionId = $backgroundAuctionIds[random_int(0, count($backgroundAuctionIds) - 1)];
                $amount = $basePriceMinorUnits + random_int(10, 100_000);
                $requests[] = $this->bidRequest($client, $baseUrl, $auctionId, $amount);
            }

            $this->executeConcurrently($requests);

            $elapsed = microtime(true) - $iterationStart;
            $remaining = 1.0 - $elapsed;
            if ($remaining > 0) {
                usleep((int) ($remaining * 1_000_000));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * @return array{client: Client, request: Request, endpoint: string}
     */
    private function bidRequest(Client $client, string $baseUrl, string $auctionId, int $amountMinorUnits): array
    {
        $endpoint = "/auctions/{$auctionId}/bids";
        $jar = $client->getConfig('cookies');
        $xsrfToken = $this->xsrfTokenFrom($jar, $baseUrl);

        $request = new Request(
            'POST',
            $endpoint,
            [
                'X-XSRF-TOKEN' => $xsrfToken,
                'Idempotency-Key' => (string) Str::uuid(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json_encode(['amount_minor_units' => $amountMinorUnits, 'currency' => 'USD']),
        );

        return ['client' => $client, 'request' => $request, 'endpoint' => $endpoint];
    }

    /**
     * @param  list<array{client: Client, request: Request, endpoint: string}>  $entries
     */
    private function executeConcurrently(array $entries): void
    {
        $promises = [];

        foreach ($entries as $index => $entry) {
            $startedAt = microtime(true);
            $promises[$index] = $entry['client']->sendAsync($entry['request'])->then(
                function ($response) use ($entry, $startedAt) {
                    $this->results[] = [
                        'endpoint' => $entry['endpoint'],
                        'status' => $response->getStatusCode(),
                        'ms' => (microtime(true) - $startedAt) * 1000,
                    ];
                },
                function (GuzzleException $exception) use ($entry, $startedAt) {
                    $this->results[] = [
                        'endpoint' => $entry['endpoint'],
                        'status' => 0,
                        'ms' => (microtime(true) - $startedAt) * 1000,
                    ];
                },
            );
        }

        Utils::settle($promises)->wait();
    }

    private function queueDepth(): int
    {
        try {
            return (int) Redis::connection('queue')->llen('queues:default');
        } catch (\Throwable) {
            return -1;
        }
    }

    private function printReport(
        float $wallClockSeconds,
        int $failedJobsBefore,
        int $failedJobsAfter,
        int $queueDepthBefore,
        int $queueDepthAfter,
    ): void {
        $total = count($this->results);
        $successes = array_filter($this->results, fn (array $r): bool => $r['status'] >= 200 && $r['status'] < 300);
        $clientErrors = array_filter($this->results, fn (array $r): bool => $r['status'] >= 400 && $r['status'] < 500);
        $serverErrors = array_filter($this->results, fn (array $r): bool => $r['status'] >= 500 || $r['status'] === 0);
        $latencies = array_column($this->results, 'ms');
        sort($latencies);
        $count = count($latencies);

        $percentile = function (float $p) use ($latencies, $count): float {
            if ($count === 0) {
                return 0.0;
            }

            return $latencies[min($count - 1, (int) ceil($p * $count) - 1)];
        };

        $this->newLine();
        $this->info('=== Load Test Results ===');
        $this->line("Wall clock: {$wallClockSeconds}s");
        $this->line("Total requests: {$total}");
        $this->line('2xx: '.count($successes).' | 4xx: '.count($clientErrors).' | 5xx/network-error: '.count($serverErrors));
        $this->line(sprintf('Latency ms — p50: %.1f, p95: %.1f, p99: %.1f, max: %.1f', $percentile(0.50), $percentile(0.95), $percentile(0.99), $count > 0 ? max($latencies) : 0));
        $this->line("failed_jobs before: {$failedJobsBefore}, after: {$failedJobsAfter} (delta: ".($failedJobsAfter - $failedJobsBefore).')');
        $this->line("Redis default-queue depth before: {$queueDepthBefore}, after: {$queueDepthAfter}");
    }
}
