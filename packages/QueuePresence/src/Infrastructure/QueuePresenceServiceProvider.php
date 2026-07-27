<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentPresenceSessionRepository;

final class QueuePresenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PresenceSessionRepository::class, EloquentPresenceSessionRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
