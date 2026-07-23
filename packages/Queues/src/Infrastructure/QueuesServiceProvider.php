<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentQueueRepository;

final class QueuesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QueueRepository::class, EloquentQueueRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
