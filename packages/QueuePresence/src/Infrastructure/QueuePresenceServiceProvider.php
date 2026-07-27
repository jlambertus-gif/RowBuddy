<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\QueuePresence\Contracts\DomainEventPublisher;
use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentGpsPingRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentPresenceSessionRepository;
use RowBuddy\QueuePresence\Infrastructure\Events\LaravelDomainEventPublisher;

final class QueuePresenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PresenceSessionRepository::class, EloquentPresenceSessionRepository::class);
        $this->app->bind(GpsPingRepository::class, EloquentGpsPingRepository::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
