<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Disputes\Application\FixedDisputeFilingDeadlinePolicy;
use RowBuddy\Disputes\Contracts\DisputeFilingDeadlinePolicy;
use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Contracts\DomainEventPublisher;
use RowBuddy\Disputes\Infrastructure\Eloquent\EloquentDisputeRepository;
use RowBuddy\Disputes\Infrastructure\Events\LaravelDomainEventPublisher;

final class DisputesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DisputeRepository::class, EloquentDisputeRepository::class);

        $this->app->bind(DomainEventPublisher::class, function ($app) {
            return new LaravelDomainEventPublisher($app->make(Dispatcher::class));
        });

        // Provisional MVP configuration value, not a permanent domain
        // invariant (ADR-021 §3) — isolated in exactly this one binding,
        // reading from config/disputes.php (env-overridable). Resolved
        // via the Repository contract rather than the global config()
        // helper, since this package's own PHPStan analysis never
        // bootstraps the framework.
        $this->app->bind(DisputeFilingDeadlinePolicy::class, function ($app) {
            $config = $app->make(Repository::class);
            $days = (int) $config->get('disputes.filing_window_days', 7);

            return new FixedDisputeFilingDeadlinePolicy($days * 86400);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
