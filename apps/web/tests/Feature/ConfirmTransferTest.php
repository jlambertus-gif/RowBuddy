<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Administration\Infrastructure\Eloquent\AccountStandingModel;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

/**
 * @return array{0: string, 1: User, 2: User}
 */
function createConfirmableTransferFixture(array $overrides = []): array
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
        'status' => 'issued',
        ...$overrides,
    ]);

    return [$transferId, $seller, $buyer];
}

function withinGeofencePayload(array $extra = []): array
{
    return ['latitude' => 32.7157, 'longitude' => -117.1611, ...$extra];
}

it('redirects an unauthenticated seller confirmation to login and confirms nothing', function () {
    [$transferId] = createConfirmableTransferFixture();

    $response = $this->post("/transfers/{$transferId}/confirm-as-seller", withinGeofencePayload(['qr_token' => 'plaintext-token']));

    $response->assertRedirect('/login');
    expect(TransferModel::find($transferId)->seller_confirmed_at)->toBeNull();
});

it('lets the correct seller confirm with the correct QR token inside the geofence', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();

    $response = $this->actingAs($seller)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        withinGeofencePayload(['qr_token' => 'plaintext-token']),
    );

    $response->assertOk();
    expect(TransferModel::find($transferId)->seller_confirmed_at)->not->toBeNull();
});

it('lets the correct buyer confirm inside the geofence', function () {
    [$transferId, , $buyer] = createConfirmableTransferFixture();

    $response = $this->actingAs($buyer)->post("/transfers/{$transferId}/confirm-as-buyer", withinGeofencePayload());

    $response->assertOk();
    expect(TransferModel::find($transferId)->buyer_confirmed_at)->not->toBeNull();
});

it('rejects a seller confirmation with the wrong QR token, deriving bidderId/sellerId only from the session', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();

    $response = $this->actingAs($seller)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        withinGeofencePayload(['qr_token' => 'wrong-token']),
    );

    $response->assertStatus(422);
    expect(TransferModel::find($transferId)->seller_confirmed_at)->toBeNull();
});

it('rejects a confirmation outside the geofence', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();

    $response = $this->actingAs($seller)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        ['qr_token' => 'plaintext-token', 'latitude' => 40.7128, 'longitude' => -74.0060],
    );

    $response->assertStatus(422);
});

it('rejects the buyer attempting to confirm as the seller — an IDOR/cross-role attempt', function () {
    [$transferId, , $buyer] = createConfirmableTransferFixture();

    $response = $this->actingAs($buyer)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        withinGeofencePayload(['qr_token' => 'plaintext-token']),
    );

    $response->assertStatus(403);
    expect(TransferModel::find($transferId)->seller_confirmed_at)->toBeNull();
});

it('rejects the seller attempting to confirm as the buyer — an IDOR/cross-role attempt', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();

    $response = $this->actingAs($seller)->post("/transfers/{$transferId}/confirm-as-buyer", withinGeofencePayload());

    $response->assertStatus(403);
    expect(TransferModel::find($transferId)->buyer_confirmed_at)->toBeNull();
});

it('rejects a completely unrelated authenticated user attempting to confirm — an IDOR attempt', function () {
    [$transferId] = createConfirmableTransferFixture();
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->post("/transfers/{$transferId}/confirm-as-buyer", withinGeofencePayload());

    $response->assertStatus(403);
});

it('never accepts a client-supplied buyerId/sellerId, deriving the requester exclusively from the session', function () {
    [$transferId, $seller, $buyer] = createConfirmableTransferFixture();

    $response = $this->actingAs($buyer)->post(
        "/transfers/{$transferId}/confirm-as-buyer",
        withinGeofencePayload(['buyer_id' => $seller->id, 'seller_id' => 999999]),
    );

    $response->assertOk();
    expect((string) TransferModel::find($transferId)->buyer_id)->toBe((string) $buyer->id);
});

it('returns 404 for an unknown transfer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(
        '/transfers/'.(string) Str::uuid().'/confirm-as-buyer',
        withinGeofencePayload(),
    );

    $response->assertNotFound();
});

it('rejects confirming an already-confirmed transfer a second time — replay protection', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();
    $this->actingAs($seller)->post("/transfers/{$transferId}/confirm-as-seller", withinGeofencePayload(['qr_token' => 'plaintext-token']))
        ->assertOk();

    $response = $this->actingAs($seller)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        withinGeofencePayload(['qr_token' => 'plaintext-token']),
    );

    $response->assertStatus(422);
});

it('rejects confirming an already-fully-confirmed transfer', function () {
    [$transferId, $seller, $buyer] = createConfirmableTransferFixture();
    $this->actingAs($seller)->post("/transfers/{$transferId}/confirm-as-seller", withinGeofencePayload(['qr_token' => 'plaintext-token']))->assertOk();
    $this->actingAs($buyer)->post("/transfers/{$transferId}/confirm-as-buyer", withinGeofencePayload())->assertOk();

    $response = $this->actingAs($buyer)->post("/transfers/{$transferId}/confirm-as-buyer", withinGeofencePayload());

    $response->assertStatus(422);
});

it('rejects confirming an expired transfer window', function () {
    [$transferId, $seller] = createConfirmableTransferFixture([
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($seller)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        withinGeofencePayload(['qr_token' => 'plaintext-token']),
    );

    $response->assertStatus(422);
    expect(TransferModel::find($transferId)->status)->toBe('expired');
});

it('is idempotent-safe against duplicate request delivery: two identical concurrent-style seller confirmations only the first succeeds', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();

    $first = $this->actingAs($seller)->post("/transfers/{$transferId}/confirm-as-seller", withinGeofencePayload(['qr_token' => 'plaintext-token']));
    $duplicate = $this->actingAs($seller)->post("/transfers/{$transferId}/confirm-as-seller", withinGeofencePayload(['qr_token' => 'plaintext-token']));

    $first->assertOk();
    $duplicate->assertStatus(422);
});

it('allows a suspended user to complete an existing obligation where ADR-026 permits it', function () {
    [$transferId, $seller] = createConfirmableTransferFixture();
    AccountStandingModel::query()->create([
        'user_id' => $seller->id,
        'state' => 'suspended',
    ]);

    $response = $this->actingAs($seller)->post(
        "/transfers/{$transferId}/confirm-as-seller",
        withinGeofencePayload(['qr_token' => 'plaintext-token']),
    );

    $response->assertOk();
});

it('has en and es translations for every transfers message key', function () {
    $basePath = dirname(__DIR__, 2);
    $en = require "{$basePath}/lang/en/transfers.php";
    $es = require "{$basePath}/lang/es/transfers.php";

    expect(array_keys($en))->toBe(array_keys($es));
});
