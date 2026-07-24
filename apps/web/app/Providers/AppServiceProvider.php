<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Support\SystemClock;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared-kernel contracts have no service provider of their own
        // (the package is framework-agnostic by design), so composition-
        // root bindings for them live here rather than in any one
        // module's provider.
        $this->app->singleton(ClockInterface::class, SystemClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A minimal is_admin flag stands in for the Identity module's
        // future roles/permissions model (see the migration adding this
        // column) — enough to authorize the Sprint 1 Administration
        // moderation queue without inventing a full roles system early.
        Gate::define('queues.moderate', static fn (User $user): bool => $user->is_admin);
    }
}
