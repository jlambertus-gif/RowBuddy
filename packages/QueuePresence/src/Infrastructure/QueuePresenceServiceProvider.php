<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\QueuePresence\Contracts\ConfidenceScoreRepository;
use RowBuddy\QueuePresence\Contracts\DomainEventPublisher;
use RowBuddy\QueuePresence\Contracts\EvidencePhotoRepository;
use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\Contracts\ImageMetadataStripper;
use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentConfidenceScoreRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentEvidencePhotoRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentGpsPingRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentPresenceSessionRepository;
use RowBuddy\QueuePresence\Infrastructure\Events\LaravelDomainEventPublisher;
use RowBuddy\QueuePresence\Infrastructure\Images\GdImageMetadataStripper;

final class QueuePresenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PresenceSessionRepository::class, EloquentPresenceSessionRepository::class);
        $this->app->bind(GpsPingRepository::class, EloquentGpsPingRepository::class);
        $this->app->bind(EvidencePhotoRepository::class, EloquentEvidencePhotoRepository::class);
        $this->app->bind(ConfidenceScoreRepository::class, EloquentConfidenceScoreRepository::class);
        $this->app->bind(ImageMetadataStripper::class, GdImageMetadataStripper::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);

        // EvidenceStorage is deliberately NOT bound here: its only
        // implementation (Laravel's private local disk) needs a booted
        // framework container (Storage facade, signed-route URL
        // generation) that this package's own standalone tests never
        // boot — apps/web's composition root binds it instead, the same
        // reasoning as QueueGeofenceLookup's cross-module adapter.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
