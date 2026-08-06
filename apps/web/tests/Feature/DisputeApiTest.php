<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Disputes\Infrastructure\Eloquent\DisputeModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 4 (ADR-028 §3). The first HTTP surface
 * DisputeFilingService has ever had — no existing web-route test file
 * to lean on for domain-rule coverage, unlike Sprints 1-3's API tests.
 */
function createConfirmedTransferForDisputeFixture(array $overrides = []): array
{
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $queueId = (string) Str::uuid();
    $auctionId = (string) Str::uuid();
    $transferId = (string) Str::uuid();

    QueueModel::query()->create([
        'id' => $queueId,
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'center_latitude' => 32.7157,
        'center_longitude' => -117.1611,
        'radius_meters' => 200,
        'authorship' => 'admin_curated',
        'organizer_reference' => 'venue-42',
        'status' => 'published',
    ]);

    AuctionModel::query()->create([
        'id' => $auctionId,
        'queue_id' => $queueId,
        'seller_id' => $seller->id,
        'presence_session_id' => (string) Str::uuid(),
        'starting_price_minor_units' => 1000,
        'starting_price_currency' => 'USD',
        'opened_at' => now(),
        'closes_at' => now()->addMinutes(30),
        'status' => 'won',
        'winning_bid_id' => (string) Str::uuid(),
        'winning_amount_minor_units' => 1500,
        'winning_amount_currency' => 'USD',
    ]);

    TransferModel::query()->create([
        'id' => $transferId,
        'auction_id' => $auctionId,
        'winning_bid_id' => (string) Str::uuid(),
        'seller_id' => $seller->id,
        'buyer_id' => $buyer->id,
        'qr_token_hash' => hash('sha256', 'plaintext-token'),
        'issued_at' => now(),
        'expires_at' => now()->addHours(24),
        'seller_confirmed_at' => now(),
        'buyer_confirmed_at' => now(),
        'confirmed_at' => now(),
        'status' => 'confirmed',
        ...$overrides,
    ]);

    return [$transferId, $seller, $buyer];
}

// --- POST /transfers/{id}/disputes ---

it('rejects an unauthenticated dispute-filing attempt (authorization)', function () {
    [$transferId] = createConfirmedTransferForDisputeFixture();

    $this->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'The item never arrived at the venue.'])
        ->assertUnauthorized();
});

it('lets the buyer file a dispute via a Sanctum token (feature, contract)', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", [
            'reason' => 'The seller did not show up at the agreed location.',
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'transfer_id', 'reason', 'status', 'opened_at', 'resolution_outcome', 'refund_amount', 'resolved_at'],
    ]);
    expect($response->json('data.status'))->toBe('opened')
        ->and($response->json('data.resolution_outcome'))->toBeNull();

    $model = DisputeModel::query()->where('transfer_id', $transferId)->first();
    expect((string) $model->buyer_id)->toBe((string) $buyer->id);
});

it('rejects the seller attempting to file a dispute — an IDOR/cross-role attempt', function () {
    [$transferId, $seller] = createConfirmedTransferForDisputeFixture();
    $token = $seller->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'Attempting to file as the seller.'])
        ->assertStatus(403);
});

it('rejects an unrelated authenticated stranger — an IDOR attempt', function () {
    [$transferId] = createConfirmedTransferForDisputeFixture();
    $stranger = User::factory()->create();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'I have no relation to this transfer.'])
        ->assertStatus(403);
});

it('never accepts a client-supplied buyerId, deriving it exclusively from the token', function () {
    [$transferId, $seller, $buyer] = createConfirmedTransferForDisputeFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", [
            'reason' => 'A perfectly valid filing reason for this transfer.',
            'buyer_id' => (string) $seller->id,
        ]);

    $response->assertCreated();
    expect((string) DisputeModel::query()->where('transfer_id', $transferId)->first()->buyer_id)->toBe((string) $buyer->id);
});

it('rejects filing against a transfer that is not confirmed', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture(['status' => 'issued']);
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'This transfer was never confirmed.'])
        ->assertStatus(404);
});

it('rejects a second dispute filing for the same transfer', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'First filing attempt for this transfer.'])
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'Second filing attempt for this transfer.'])
        ->assertStatus(409);
});

it('rejects filing after the deadline window has elapsed', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture(['confirmed_at' => now()->subDays(30)]);
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'Filing well after the deadline window.'])
        ->assertStatus(422);
});

it('rejects a reason that is too short', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'short'])
        ->assertStatus(422);
});

// --- GET /disputes/{id} ---

it('rejects an unauthenticated dispute-status request (authorization)', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture();
    $token = $buyer->createToken('device')->plainTextToken;
    $disputeId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'A valid filing reason for this transfer.'])
        ->json('data.id');

    // Both the persistent default Authorization header AND the cached
    // guard-resolved user from the request above must be cleared, or
    // this "unauthenticated" request silently still resolves as the
    // buyer. See project_sanctum_guard_memoization_gotcha.md.
    $this->app['auth']->forgetGuards();
    $this->withoutHeader('Authorization')->getJson("/api/v1/disputes/{$disputeId}")->assertUnauthorized();
});

it('lets the filing buyer view their own dispute status (feature, contract)', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture();
    $token = $buyer->createToken('device')->plainTextToken;
    $disputeId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'A valid filing reason for this transfer.'])
        ->json('data.id');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/disputes/{$disputeId}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($disputeId)
        ->and($response->json('data.status'))->toBe('opened');
});

it('rejects the seller from viewing the buyer-only dispute status view — an IDOR/cross-role attempt', function () {
    [$transferId, $seller, $buyer] = createConfirmedTransferForDisputeFixture();
    $buyerToken = $buyer->createToken('device')->plainTextToken;
    $disputeId = $this->withHeader('Authorization', "Bearer {$buyerToken}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'A valid filing reason for this transfer.'])
        ->json('data.id');

    // See project_sanctum_guard_memoization_gotcha.md — required whenever
    // a test method authenticates as a second, different user.
    $this->app['auth']->forgetGuards();

    $sellerToken = $seller->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$sellerToken}")
        ->getJson("/api/v1/disputes/{$disputeId}")
        ->assertStatus(403);
});

it('rejects an unrelated authenticated stranger from viewing the dispute — an IDOR attempt', function () {
    [$transferId, , $buyer] = createConfirmedTransferForDisputeFixture();
    $buyerToken = $buyer->createToken('device')->plainTextToken;
    $disputeId = $this->withHeader('Authorization', "Bearer {$buyerToken}")
        ->postJson("/api/v1/transfers/{$transferId}/disputes", ['reason' => 'A valid filing reason for this transfer.'])
        ->json('data.id');

    $this->app['auth']->forgetGuards();

    $stranger = User::factory()->create();
    $strangerToken = $stranger->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->getJson("/api/v1/disputes/{$disputeId}")
        ->assertStatus(403);
});

it('returns 404 for an unknown dispute', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/disputes/'.(string) Str::uuid())
        ->assertNotFound();
});

it('has en and es translations for every new disputes filing message key', function () {
    $basePath = dirname(__DIR__, 2);
    $en = require "{$basePath}/lang/en/disputes.php";
    $es = require "{$basePath}/lang/es/disputes.php";

    expect(array_keys($en['filing']['errors']))->toBe(array_keys($es['filing']['errors']))
        ->and(array_keys($en['filing']['fields']))->toBe(array_keys($es['filing']['fields']));
});
