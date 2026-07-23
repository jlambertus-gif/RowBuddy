<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Queues\Contracts\JurisdictionRuleRepository;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Contracts\RestrictedCategoryRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentJurisdictionRuleRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentQueueRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentRestrictedCategoryRepository;

final class QueuesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QueueRepository::class, EloquentQueueRepository::class);
        $this->app->bind(RestrictedCategoryRepository::class, EloquentRestrictedCategoryRepository::class);
        $this->app->bind(JurisdictionRuleRepository::class, EloquentJurisdictionRuleRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
