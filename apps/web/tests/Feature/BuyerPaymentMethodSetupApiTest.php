<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodGateway;
use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\Infrastructure\Eloquent\BuyerPaymentMethodModel;
use RowBuddy\Payments\ValueObjects\ConfirmedPaymentMethod;
use RowBuddy\Payments\ValueObjects\SetupIntentDraft;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 3. Both routes are thin auth:sanctum mirrors of the
 * existing web session-guarded /buyer-payment-methods* routes, reusing
 * BeginBuyerPaymentMethodSetupController/CompleteBuyerPaymentMethodSetupController
 * verbatim — every domain-rule branch (mismatch, not-confirmed,
 * provider-unavailable, validation) is already covered by
 * tests/Feature/BuyerPaymentMethodSetupTest.php against those existing
 * routes, since they are the identical controllers/service. These tests
 * cover only what is new here: the sanctum-guarded wiring itself.
 */
function fakeApiBuyerPaymentMethodGateway(): BuyerPaymentMethodGateway
{
    return new class implements BuyerPaymentMethodGateway
    {
        public bool $succeeded = true;

        public ?string $mismatchedBuyerId = null;

        public function createCustomer(string $buyerId): string
        {
            return 'cus_fake_'.$buyerId;
        }

        public function createSetupIntent(string $stripeCustomerId): SetupIntentDraft
        {
            return new SetupIntentDraft($stripeCustomerId, 'seti_fake_123', 'seti_fake_123_secret_abc');
        }

        public function retrieveConfirmedPaymentMethod(string $setupIntentId, string $buyerId): ConfirmedPaymentMethod
        {
            if (! $this->succeeded) {
                throw SetupIntentNotConfirmed::forSetupIntentId($setupIntentId);
            }

            if ($this->mismatchedBuyerId !== null) {
                throw SetupIntentBuyerMismatch::forSetupIntentId($setupIntentId);
            }

            return new ConfirmedPaymentMethod('cus_fake_'.$buyerId, 'pm_fake_'.$buyerId);
        }
    };
}

it('rejects an unauthenticated setup-intent attempt (authorization)', function () {
    $this->postJson('/api/v1/buyer-payment-methods/setup-intent')->assertUnauthorized();
});

it('begins setup via a Sanctum token and returns only the client secret and publishable key (feature, contract)', function () {
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => fakeApiBuyerPaymentMethodGateway());
    $buyer = User::factory()->create();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/buyer-payment-methods/setup-intent');

    $response->assertOk();
    $body = $response->json('data');
    expect(array_keys($body))->toBe(['client_secret', 'publishable_key'])
        ->and($body['client_secret'])->toBe('seti_fake_123_secret_abc');
});

it('rejects an unauthenticated setup-completion attempt (authorization)', function () {
    $this->postJson('/api/v1/buyer-payment-methods', ['setup_intent_id' => 'seti_fake_123'])
        ->assertUnauthorized();
});

it('completes setup via a Sanctum token, persisting it against the token owner, never a client-supplied buyer (feature, contract, IDOR)', function () {
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => fakeApiBuyerPaymentMethodGateway());
    $buyer = User::factory()->create();
    $impersonatedTarget = User::factory()->create();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/buyer-payment-methods', [
            'setup_intent_id' => 'seti_fake_123',
            // BuyerPaymentMethodSetupService derives buyerId exclusively
            // from the authenticated token — this extra field is
            // deliberately unexpected input, proving it is ignored.
            'buyer_id' => (string) $impersonatedTarget->id,
        ]);

    $response->assertOk();
    $body = $response->json('data');
    expect(array_keys($body))->toBe(['saved', 'saved_at'])
        ->and($body['saved'])->toBeTrue();

    $model = BuyerPaymentMethodModel::query()->where('buyer_id', $buyer->id)->first();
    expect($model)->not->toBeNull()
        ->and($model->stripe_payment_method_id)->toBe('pm_fake_'.$buyer->id)
        ->and(BuyerPaymentMethodModel::query()->where('buyer_id', $impersonatedTarget->id)->exists())->toBeFalse();
});

it('rejects completing setup with a SetupIntent belonging to a different buyer, via a Sanctum token (IDOR)', function () {
    $gateway = fakeApiBuyerPaymentMethodGateway();
    $gateway->mismatchedBuyerId = '999';
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => $gateway);
    $buyer = User::factory()->create();
    $token = $buyer->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/buyer-payment-methods', ['setup_intent_id' => 'seti_stolen']);

    $response->assertStatus(403);
    expect(BuyerPaymentMethodModel::query()->where('buyer_id', $buyer->id)->exists())->toBeFalse();
});
