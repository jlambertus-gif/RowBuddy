<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Queues\Contracts\DomainEventPublisher;
use RowBuddy\Queues\Contracts\JurisdictionRuleRepository;
use RowBuddy\Queues\Contracts\JurisdictionRuleWriteRepository;
use RowBuddy\Queues\Contracts\QueueDiscoveryRepository;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Contracts\RestrictedCategoryRepository;
use RowBuddy\Queues\Contracts\RestrictedCategoryWriteRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentJurisdictionRuleRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentJurisdictionRuleWriteRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentQueueRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentRestrictedCategoryRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentRestrictedCategoryWriteRepository;
use RowBuddy\Queues\Infrastructure\Events\LaravelDomainEventPublisher;
use RowBuddy\Queues\Infrastructure\PostGIS\PostGISQueueDiscoveryRepository;

final class QueuesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QueueRepository::class, EloquentQueueRepository::class);
        $this->app->bind(RestrictedCategoryRepository::class, EloquentRestrictedCategoryRepository::class);
        $this->app->bind(JurisdictionRuleRepository::class, EloquentJurisdictionRuleRepository::class);
        $this->app->bind(RestrictedCategoryWriteRepository::class, EloquentRestrictedCategoryWriteRepository::class);
        $this->app->bind(JurisdictionRuleWriteRepository::class, EloquentJurisdictionRuleWriteRepository::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);
        $this->app->bind(QueueDiscoveryRepository::class, PostGISQueueDiscoveryRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
