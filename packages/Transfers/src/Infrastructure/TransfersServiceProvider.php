<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Transfers\Application\FixedTransferWindowPolicy;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;
use RowBuddy\Transfers\Contracts\ImageMetadataStripper;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Contracts\TransferWindowPolicy;
use RowBuddy\Transfers\Infrastructure\Eloquent\EloquentTransferRepository;
use RowBuddy\Transfers\Infrastructure\Events\LaravelDomainEventPublisher;
use RowBuddy\Transfers\Infrastructure\Images\GdImageMetadataStripper;

final class TransfersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TransferRepository::class, EloquentTransferRepository::class);

        $this->app->bind(DomainEventPublisher::class, function ($app) {
            return new LaravelDomainEventPublisher($app->make(Dispatcher::class));
        });

        // Pure PHP (GD extension only), no framework dependency — bound
        // here rather than at the composition root, mirroring
        // QueuePresence's own identical binding.
        $this->app->bind(ImageMetadataStripper::class, GdImageMetadataStripper::class);

        // Provisional MVP configuration value, not a permanent domain
        // invariant (ADR-018 §1) — isolated in exactly this one binding,
        // reading from config/transfers.php (env-overridable). Resolved
        // via the Repository contract rather than the global config()
        // helper, since this package's own PHPStan analysis never
        // bootstraps the framework.
        $this->app->bind(TransferWindowPolicy::class, function ($app) {
            $config = $app->make(Repository::class);
            $hours = (int) $config->get('transfers.window_hours', 24);

            return new FixedTransferWindowPolicy($hours * 3600);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
