<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Infrastructure;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Ratings\Contracts\DomainEventPublisher;
use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Infrastructure\Eloquent\EloquentRatingRepository;
use RowBuddy\Ratings\Infrastructure\Events\LaravelDomainEventPublisher;

final class RatingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RatingRepository::class, EloquentRatingRepository::class);

        $this->app->bind(DomainEventPublisher::class, function ($app) {
            return new LaravelDomainEventPublisher($app->make(Dispatcher::class));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
