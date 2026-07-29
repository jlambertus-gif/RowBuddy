<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Infrastructure\Eloquent\EloquentDisputeRepository;

final class DisputesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DisputeRepository::class, EloquentDisputeRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
