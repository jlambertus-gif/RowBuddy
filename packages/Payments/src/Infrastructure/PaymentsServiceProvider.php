<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Payments\Application\FixedPlatformFeePolicy;
use RowBuddy\Payments\Application\FixedTransactionValueLimitPolicy;
use RowBuddy\Payments\Contracts\PlatformFeePolicy;
use RowBuddy\Payments\Contracts\TransactionValueLimitPolicy;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }
}
