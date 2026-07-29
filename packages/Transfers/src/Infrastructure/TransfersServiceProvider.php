<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Infrastructure\Eloquent\EloquentTransferRepository;

final class TransfersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TransferRepository::class, EloquentTransferRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
