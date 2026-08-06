<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Administration\Infrastructure\Eloquent\AccountStandingModel;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Ratings\Infrastructure\Eloquent\RatingModel;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 4 (ADR-028 §3). Unlike Sprints 1-3's API tests, there is
 * no existing web-route test file to lean on for domain-rule coverage —
 * this is the first HTTP surface RatingSubmissionService/
 * RatingRevealEvaluator have ever had, on any client. These tests cover
 * both the sanctum-guarded wiring and the domain rules it now exposes.
 */
function createConfirmedTransferForRatingFixture(): array
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
    ]);

    return [$transferId, $seller, $buyer];
}

// --- POST /transfers/{id}/ratings ---

it('rejects an unauthenticated rating submission (authorization)', function () {
    [$transferId] = createConfirmedTransferForRatingFixture();

    $this->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertUnauthorized();
});

it('lets a confirmed transfer participant rate the other party (feature, contract)', function () {
    [$transferId, $seller, $buyer] = createConfirmedTransferForRatingFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", [
            'score' => 5,
            'comment' => 'Smooth handoff, thank you.',
        ]);

    $response->assertCreated();
    $response->assertJsonStructure(['data' => ['id', 'transfer_id', 'score', 'comment', 'submitted_at']]);
    expect($response->json('data.score'))->toBe(5)
        ->and($response->json('data.comment'))->toBe('Smooth handoff, thank you.');

    $model = RatingModel::query()->where('transfer_id', $transferId)->first();
    expect((string) $model->rater_id)->toBe((string) $buyer->id)
        ->and((string) $model->ratee_id)->toBe((string) $seller->id);
});

it('never accepts a client-supplied ratee, deriving it exclusively from the transfer (IDOR)', function () {
    [$transferId, , $buyer] = createConfirmedTransferForRatingFixture();
    $impersonatedTarget = User::factory()->create();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", [
            'score' => 5,
            'ratee_id' => (string) $impersonatedTarget->id,
        ]);

    $response->assertCreated();
    expect(RatingModel::query()->where('ratee_id', $impersonatedTarget->id)->exists())->toBeFalse();
});

it('rejects a rating attempt from an unrelated authenticated stranger (IDOR)', function () {
    [$transferId] = createConfirmedTransferForRatingFixture();
    $stranger = User::factory()->create();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertStatus(403);
});

it('rejects a rating attempt against a transfer that is not confirmed', function () {
    [$transferId, , $buyer] = createConfirmedTransferForRatingFixture();
    TransferModel::query()->where('id', $transferId)->update(['status' => 'issued']);
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertStatus(404);
});

it('rejects a second rating attempt from the same rater on the same transfer', function () {
    [$transferId, , $buyer] = createConfirmedTransferForRatingFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 1])
        ->assertStatus(409);
});

it('rejects a rating from a suspended account', function () {
    [$transferId, , $buyer] = createConfirmedTransferForRatingFixture();
    AccountStandingModel::query()->create(['user_id' => $buyer->id, 'state' => 'suspended']);
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertStatus(403);
});

it('rejects a score outside 1-5', function () {
    [$transferId, , $buyer] = createConfirmedTransferForRatingFixture();
    $token = $buyer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 6])
        ->assertStatus(422);
});

// --- GET /transfers/{id}/ratings ---

it('rejects an unauthenticated ratings list request (authorization)', function () {
    [$transferId] = createConfirmedTransferForRatingFixture();

    $this->getJson("/api/v1/transfers/{$transferId}/ratings")->assertUnauthorized();
});

it('rejects an unrelated authenticated stranger from listing ratings (IDOR)', function () {
    [$transferId] = createConfirmedTransferForRatingFixture();
    $stranger = User::factory()->create();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/transfers/{$transferId}/ratings")
        ->assertStatus(403);
});

it('shows my own rating immediately but hides an unrevealed counterpart rating (feature, contract, double-blind)', function () {
    [$transferId, $seller, $buyer] = createConfirmedTransferForRatingFixture();
    $buyerToken = $buyer->createToken('device')->plainTextToken;
    $sellerToken = $seller->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$buyerToken}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertCreated();

    // The AuthManager/RequestGuard caches the buyer's resolved identity
    // from the request above across this test method — without
    // resetting it, the request below would be silently re-attributed
    // to the buyer instead of the seller's own bearer token (the same
    // test-harness artifact PlaceBidApiTest/TransferApiTest hit).
    $this->app['auth']->forgetGuards();

    $response = $this->withHeader('Authorization', "Bearer {$sellerToken}")
        ->getJson("/api/v1/transfers/{$transferId}/ratings");

    $response->assertOk();
    expect($response->json('data.mine'))->toBeNull()
        ->and($response->json('data.counterpart'))->toBeNull()
        ->and($response->json('data.counterpart_submitted'))->toBeTrue();
});

it('reveals the counterpart rating once both parties have submitted', function () {
    [$transferId, $seller, $buyer] = createConfirmedTransferForRatingFixture();
    $buyerToken = $buyer->createToken('device')->plainTextToken;
    $sellerToken = $seller->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$buyerToken}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 5])
        ->assertCreated();

    // See the identical note in the test above.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$sellerToken}")
        ->postJson("/api/v1/transfers/{$transferId}/ratings", ['score' => 4])
        ->assertCreated();

    $this->app['auth']->forgetGuards();

    $response = $this->withHeader('Authorization', "Bearer {$sellerToken}")
        ->getJson("/api/v1/transfers/{$transferId}/ratings");

    $response->assertOk();
    expect($response->json('data.mine.score'))->toBe(4)
        ->and($response->json('data.counterpart.score'))->toBe(5);
});

it('returns 404 for an unknown transfer', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/transfers/'.(string) Str::uuid().'/ratings')
        ->assertNotFound();
});

it('has en and es translations for every ratings message key', function () {
    $basePath = dirname(__DIR__, 2);
    $en = require "{$basePath}/lang/en/ratings.php";
    $es = require "{$basePath}/lang/es/ratings.php";

    expect(array_keys($en['fields']))->toBe(array_keys($es['fields']))
        ->and(array_keys($en['errors']))->toBe(array_keys($es['errors']));
});
