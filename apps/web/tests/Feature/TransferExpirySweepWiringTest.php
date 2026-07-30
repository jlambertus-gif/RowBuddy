<?php

declare(strict_types=1);

use App\Jobs\EvaluateTransferExpiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\Transfers\Application\TransferExpirySweepService;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

uses(RefreshDatabase::class);

it('registers the transfer expiry sweep on the application schedule', function () {
    // withSchedule()'s closure is only evaluated for schedule:* console
    // commands (Laravel defers it to avoid the cost on every request),
    // so schedule:list is the only reliable way to observe registration.
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain(EvaluateTransferExpiry::class);
});

it('expires a due transfer end-to-end when the queued job runs against the real repository', function () {
    // TransferExpired now triggers a real Notifications listener (Phase
    // 7), which needs a resolvable buyer/seller — real User rows, not
    // the placeholder '1'/'2' ids this fixture used before that wiring
    // existed.
    Mail::fake();

    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transfers = app(TransferRepository::class);
    $clock = app(ClockInterface::class);

    $transfer = Transfer::issue(
        (string) Str::uuid(),
        (string) Str::uuid(),
        (string) Str::uuid(),
        (string) $seller->id,
        (string) $buyer->id,
        hash('sha256', 'plaintext-token'),
        $clock->now()->modify('-1 hour'),
        $clock,
    );
    $transfer->releaseEvents();
    $transfers->save($transfer);

    app(EvaluateTransferExpiry::class)->handle(app(TransferExpirySweepService::class));

    expect($transfers->findById($transfer->id)->status())->toBe(TransferStatus::Expired);
});
