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
 * Fake at the HTTP boundary rather than hitting real Stripe — the real
 * Stripe test-mode integration is covered separately in
 * packages/Payments' own StripeBuyerPaymentMethodGatewayTest, skipped
 * unless real credentials are configured. This test proves the full
 * HTTP stack (routing, controller, request validation, service,
 * response shape) with no dependency on external credentials.
 */
function fakeBuyerPaymentMethodGateway(): BuyerPaymentMethodGateway
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

it('redirects unauthenticated users to login and begins no setup', function () {
    $response = $this->post('/buyer-payment-methods/setup-intent');

    $response->assertRedirect('/login');
});

it('begins setup and returns only the client secret and publishable key', function () {
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => fakeBuyerPaymentMethodGateway());
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer)->post('/buyer-payment-methods/setup-intent');

    $response->assertOk();
    $body = $response->json('data');
    expect(array_keys($body))->toBe(['client_secret', 'publishable_key'])
        ->and($body['client_secret'])->toBe('seti_fake_123_secret_abc');
});

it('completes setup and persists the buyer payment method, exposing no Stripe reference', function () {
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => fakeBuyerPaymentMethodGateway());
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer)->post('/buyer-payment-methods', ['setup_intent_id' => 'seti_fake_123']);

    $response->assertOk();
    $body = $response->json('data');
    expect(array_keys($body))->toBe(['saved', 'saved_at'])
        ->and($body['saved'])->toBeTrue()
        ->and(json_encode($body))->not->toContain('cus_')
        ->and(json_encode($body))->not->toContain('pm_');

    $model = BuyerPaymentMethodModel::query()->where('buyer_id', $buyer->id)->first();
    expect($model)->not->toBeNull()
        ->and($model->stripe_payment_method_id)->toBe('pm_fake_'.$buyer->id);
});

it('rejects completing setup with a SetupIntent that has not been confirmed yet', function () {
    $gateway = fakeBuyerPaymentMethodGateway();
    $gateway->succeeded = false;
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => $gateway);
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer)->post('/buyer-payment-methods', ['setup_intent_id' => 'seti_pending']);

    $response->assertStatus(422);
    expect(BuyerPaymentMethodModel::query()->where('buyer_id', $buyer->id)->exists())->toBeFalse();
});

it('rejects completing setup with a SetupIntent belonging to a different buyer', function () {
    $gateway = fakeBuyerPaymentMethodGateway();
    $gateway->mismatchedBuyerId = '999';
    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => $gateway);
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer)->post('/buyer-payment-methods', ['setup_intent_id' => 'seti_stolen']);

    $response->assertStatus(403);
    expect(BuyerPaymentMethodModel::query()->where('buyer_id', $buyer->id)->exists())->toBeFalse();
});

it('requires a setup_intent_id', function () {
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer)->post('/buyer-payment-methods', [], ['Accept' => 'application/json']);

    $response->assertStatus(422);
});

it('has en and es translations for every payments message key', function () {
    $basePath = dirname(__DIR__, 2);
    $en = require "{$basePath}/lang/en/payments.php";
    $es = require "{$basePath}/lang/es/payments.php";

    expect(array_keys($en))->toBe(array_keys($es));
});
