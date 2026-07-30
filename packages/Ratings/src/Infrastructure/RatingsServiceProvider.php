<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Ratings\Application\FixedRatingRevealDeadlinePolicy;
use RowBuddy\Ratings\Contracts\DomainEventPublisher;
use RowBuddy\Ratings\Contracts\RatingRepository;
use RowBuddy\Ratings\Contracts\RatingRevealDeadlinePolicy;
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

        // Provisional MVP configuration value, not a permanent domain
        // invariant (ADR-024 §5) — isolated in exactly this one binding,
        // reading from config/ratings.php (env-overridable), mirroring
        // DisputesServiceProvider's identical posture for its own
        // deadline policies.
        $this->app->bind(RatingRevealDeadlinePolicy::class, function ($app) {
            $config = $app->make(Repository::class);
            $days = (int) $config->get('ratings.reveal_window_days', 7);

            return new FixedRatingRevealDeadlinePolicy($days * 86400);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
