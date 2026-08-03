<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Payments\Application\FixedPaymentProcessingCostPolicy;
use RowBuddy\Payments\Application\FixedPlatformFeePolicy;
use RowBuddy\Payments\Application\FixedTransactionValueLimitPolicy;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodGateway;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;
use RowBuddy\Payments\Contracts\ConnectAccountGateway;
use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\Contracts\PaymentProcessingCostPolicy;
use RowBuddy\Payments\Contracts\PlatformFeePolicy;
use RowBuddy\Payments\Contracts\SellerPayoutAccountRepository;
use RowBuddy\Payments\Contracts\TransactionValueLimitPolicy;
use RowBuddy\Payments\Contracts\WebhookEventRepository;
use RowBuddy\Payments\Contracts\WebhookSignatureVerifier;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentBuyerPaymentMethodRepository;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentPaymentIntentRepository;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentSellerPayoutAccountRepository;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentWebhookEventRepository;
use RowBuddy\Payments\Infrastructure\Events\LaravelDomainEventPublisher;
use RowBuddy\Payments\Infrastructure\Stripe\StripeBuyerPaymentMethodGateway;
use RowBuddy\Payments\Infrastructure\Stripe\StripeConnectAccountGateway;
use RowBuddy\Payments\Infrastructure\Stripe\StripePaymentAuthorizationGateway;
use RowBuddy\Payments\Infrastructure\Stripe\StripeWebhookSignatureVerifier;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;
use Stripe\StripeClient;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentIntentRepository::class, EloquentPaymentIntentRepository::class);
        $this->app->bind(SellerPayoutAccountRepository::class, EloquentSellerPayoutAccountRepository::class);
        $this->app->bind(WebhookEventRepository::class, EloquentWebhookEventRepository::class);
        $this->app->bind(BuyerPaymentMethodRepository::class, EloquentBuyerPaymentMethodRepository::class);

        $this->app->bind(DomainEventPublisher::class, function ($app) {
            return new LaravelDomainEventPublisher($app->make(Dispatcher::class));
        });

        $this->app->singleton(StripeClient::class, function ($app) {
            $config = $app->make(Repository::class);

            return new StripeClient((string) $config->get('services.stripe.secret'));
        });

        $this->app->bind(ConnectAccountGateway::class, function ($app) {
            return new StripeConnectAccountGateway($app->make(StripeClient::class));
        });

        $this->app->bind(PaymentAuthorizationGateway::class, function ($app) {
            return new StripePaymentAuthorizationGateway($app->make(StripeClient::class));
        });

        $this->app->bind(WebhookSignatureVerifier::class, function ($app) {
            $config = $app->make(Repository::class);

            return new StripeWebhookSignatureVerifier((string) $config->get('services.stripe.webhook_secret'));
        });

        // ADR-027 Architecture Refinements §4: the new Stripe adapter for
        // buyer payment-method setup lives here, exactly like every other
        // Stripe adapter in this package — not in apps/web, correcting
        // the ADR's own literal "apps/web infrastructure adapter" wording
        // (a drafting inaccuracy, per José's own Sprint 3 decision).
        $this->app->bind(BuyerPaymentMethodGateway::class, function ($app) {
            return new StripeBuyerPaymentMethodGateway($app->make(StripeClient::class));
        });

        // Provisional MVP configuration values, not permanent domain
        // invariants — isolated in exactly these two bindings, each
        // reading from config/payments.php (env-overridable), so they can
        // be changed without touching PaymentIntent, FeeCalculator, or
        // any test that doesn't specifically test the fee/limit values.
        // Resolved via the Repository contract rather than the global
        // config() helper, since this package's own PHPStan analysis
        // never bootstraps the framework.
        $this->app->bind(PlatformFeePolicy::class, function ($app) {
            $config = $app->make(Repository::class);

            return new FixedPlatformFeePolicy((int) $config->get('payments.fee_percentage', 10));
        });

        $this->app->bind(TransactionValueLimitPolicy::class, function ($app) {
            $config = $app->make(Repository::class);
            $limitUsd = (int) $config->get('payments.transaction_value_limit_usd', 500);

            return new FixedTransactionValueLimitPolicy(new Money($limitUsd * 100, new Currency('USD')));
        });

        $this->app->bind(PaymentProcessingCostPolicy::class, function ($app) {
            $config = $app->make(Repository::class);
            $percentage = (int) $config->get('payments.processing_fee_percentage', 3);
            $fixedFeeCents = (int) $config->get('payments.processing_fee_fixed_cents', 30);

            return new FixedPaymentProcessingCostPolicy($percentage, new Money($fixedFeeCents, new Currency('USD')));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
