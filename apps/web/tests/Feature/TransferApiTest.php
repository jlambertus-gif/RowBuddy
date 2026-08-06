<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 3. All four routes are thin auth:sanctum mirrors of the
 * existing web session-guarded /transfers* routes, reusing
 * ShowTransferController/ShowTransferQrTokenController/
 * ConfirmTransferAsSellerController/ConfirmTransferAsBuyerController
 * verbatim — every domain-rule branch (geofence, QR-token mismatch,
 * illegal state, expiry, replay protection) is already covered by
 * ShowTransferTest.php/TransferQrTokenTest.php/ConfirmTransferTest.php
 * against those existing routes, since they are the identical
 * controllers/service. These tests cover only what is new here: the
 * sanctum-guarded wiring itself. Fixtures build the Transfer row
 * directly via Eloquent (no actingAs() anywhere in this file), so there
 * is no web-session/Sanctum-guard cross-contamination risk to guard
 * against here (unlike PlaceBidApiTest.php's fixture setup).
 */
function createApiTransferFixture(array $overrides = []): array
{
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $queueId = (string) Str::uuid();
    $auctionId = (string) Str::uuid();
    $transferId = (string) Str::uuid();

    // A real Queue/Auction pair is required, not just the Transfer row
    // itself: TransferConfirmationService's geofence cross-check resolves
    // the geofence via the auction/queue, and an unresolvable auction id
    // surfaces as the *same* NotFoundException the "unknown transfer"
    // case does — a confirm attempt against a fixture missing this would
    // 404 for the wrong reason. ShowTransferController/
    // ShowTransferQrTokenController never consult the geofence, so their
    // own fixtures (above) correctly don't need this.
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
        'status' => 'issued',
        ...$overrides,
    ]);

    return [$transferId, $seller, $buyer];
}

function withinGeofenceApiPayload(array $extra = []): array
{
    return ['latitude' => 32.7157, 'longitude' => -117.1611, ...$extra];
}

// --- GET /transfers/{id} ---

it('rejects an unauthenticated transfer-show attempt (authorization)', function () {
    [$transferId] = createApiTransferFixture();

    $this->getJson("/api/v1/transfers/{$transferId}")->assertUnauthorized();
});

it('shows the seller their own role via a Sanctum token (feature, contract)', function () {
    [$transferId, $seller] = createApiTransferFixture();
    $token = $seller->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/transfers/{$transferId}");

    $response->assertOk();
    expect($response->json('data'))->toMatchArray([
        'id' => $transferId,
        'status' => 'issued',
        'role' => 'seller',
        'seller_confirmed' => false,
        'buyer_confirmed' => false,
    ]);
});

it('shows the buyer their own role via a Sanctum token (feature, contract)', function () {
    [$transferId, , $buyer] = createApiTransferFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/transfers/{$transferId}");

    $response->assertOk();
    expect($response->json('data.role'))->toBe('buyer');
});

it('rejects an unrelated authenticated stranger via a Sanctum token — an IDOR attempt', function () {
    [$transferId] = createApiTransferFixture();
    $stranger = User::factory()->create();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/transfers/{$transferId}")
        ->assertStatus(403);
});

// --- GET /transfers/{id}/qr-token ---

it('rejects an unauthenticated qr-token retrieval attempt (authorization)', function () {
    [$transferId] = createApiTransferFixture();
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));

    $this->getJson("/api/v1/transfers/{$transferId}/qr-token")->assertUnauthorized();
});

it('lets the buyer retrieve the cached plaintext QR token via a Sanctum token (feature, contract)', function () {
    [$transferId, , $buyer] = createApiTransferFixture();
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/transfers/{$transferId}/qr-token");

    $response->assertOk();
    expect($response->json('data.qr_token'))->toBe('plaintext-token');
});

it('forbids the seller from retrieving the buyer-only QR token via a Sanctum token — an IDOR/cross-role attempt', function () {
    [$transferId, $seller] = createApiTransferFixture();
    Cache::put("transfers.{$transferId}.qr_token", 'plaintext-token', now()->addHours(24));
    $token = $seller->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/transfers/{$transferId}/qr-token")
        ->assertStatus(403);
});

// --- POST /transfers/{id}/confirm-as-seller ---

it('rejects an unauthenticated seller-confirmation attempt (authorization)', function () {
    [$transferId] = createApiTransferFixture();

    $this->postJson(
        "/api/v1/transfers/{$transferId}/confirm-as-seller",
        withinGeofenceApiPayload(['qr_token' => 'plaintext-token']),
    )->assertUnauthorized();
});

it('lets the correct seller confirm with the correct QR token via a Sanctum token (feature, contract)', function () {
    [$transferId, $seller] = createApiTransferFixture();
    $token = $seller->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(
            "/api/v1/transfers/{$transferId}/confirm-as-seller",
            withinGeofenceApiPayload(['qr_token' => 'plaintext-token']),
        );

    $response->assertOk();
    expect($response->json('data.confirmed'))->toBeTrue()
        ->and(TransferModel::find($transferId)->seller_confirmed_at)->not->toBeNull();
});

it('rejects the buyer attempting to confirm as the seller via a Sanctum token — an IDOR/cross-role attempt', function () {
    [$transferId, , $buyer] = createApiTransferFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(
            "/api/v1/transfers/{$transferId}/confirm-as-seller",
            withinGeofenceApiPayload(['qr_token' => 'plaintext-token']),
        );

    $response->assertStatus(403);
    expect(TransferModel::find($transferId)->seller_confirmed_at)->toBeNull();
});

// --- POST /transfers/{id}/confirm-as-buyer ---

it('rejects an unauthenticated buyer-confirmation attempt (authorization)', function () {
    [$transferId] = createApiTransferFixture();

    $this->postJson("/api/v1/transfers/{$transferId}/confirm-as-buyer", withinGeofenceApiPayload())
        ->assertUnauthorized();
});

it('lets the correct buyer confirm via a Sanctum token (feature, contract)', function () {
    [$transferId, , $buyer] = createApiTransferFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/confirm-as-buyer", withinGeofenceApiPayload());

    $response->assertOk();
    expect($response->json('data.confirmed'))->toBeTrue()
        ->and(TransferModel::find($transferId)->buyer_confirmed_at)->not->toBeNull();
});

it('rejects the seller attempting to confirm as the buyer via a Sanctum token — an IDOR/cross-role attempt', function () {
    [$transferId, $seller] = createApiTransferFixture();
    $token = $seller->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/confirm-as-buyer", withinGeofenceApiPayload());

    $response->assertStatus(403);
    expect(TransferModel::find($transferId)->buyer_confirmed_at)->toBeNull();
});

it('never accepts a client-supplied buyerId/sellerId via a Sanctum token, deriving the requester exclusively from it', function () {
    [$transferId, $seller, $buyer] = createApiTransferFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(
            "/api/v1/transfers/{$transferId}/confirm-as-buyer",
            withinGeofenceApiPayload(['buyer_id' => $seller->id, 'seller_id' => 999999]),
        );

    $response->assertOk();
    expect((string) TransferModel::find($transferId)->buyer_id)->toBe((string) $buyer->id);
});
