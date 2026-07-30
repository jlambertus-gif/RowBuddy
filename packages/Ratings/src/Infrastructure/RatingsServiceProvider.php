<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Infrastructure\Eloquent\EloquentRatingRepository;

final class RatingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RatingRepository::class, EloquentRatingRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
