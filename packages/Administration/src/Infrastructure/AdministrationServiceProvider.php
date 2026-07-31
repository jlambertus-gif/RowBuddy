<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Administration\Contracts\AccountStandingRepository;
use RowBuddy\Administration\Contracts\AdminActionLog;
use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\Infrastructure\Eloquent\EloquentAccountStandingRepository;
use RowBuddy\Administration\Infrastructure\Eloquent\EloquentAdminActionLog;
use RowBuddy\Administration\Infrastructure\Eloquent\EloquentAdminRoleAssignmentRepository;
use RowBuddy\Administration\Support\AdminRoleCapabilityMap;
use RowBuddy\Administration\ValueObjects\AdminCapability;

final class AdministrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdminRoleAssignmentRepository::class, EloquentAdminRoleAssignmentRepository::class);
        $this->app->singleton(AdminRoleCapabilityMap::class);
        $this->app->bind(AccountStandingRepository::class, EloquentAccountStandingRepository::class);
        $this->app->bind(AdminActionLog::class, EloquentAdminActionLog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // One Gate per capability, generically — application code
        // authorizes an ability (ADR-026 Architecture Refinements §1),
        // never compares a role directly. Deliberately depends only on
        // the generic Authenticatable contract, never App\Models\User,
        // so this package stays decoupled from apps/web's own model.
        foreach (AdminCapability::cases() as $capability) {
            Gate::define($capability->value, function (Authenticatable $user) use ($capability): bool {
                $roles = $this->app->make(AdminRoleAssignmentRepository::class);
                $map = $this->app->make(AdminRoleCapabilityMap::class);

                $role = $roles->findRoleForUser((string) $user->getAuthIdentifier());

                return $role !== null && $map->roleGrants($role, $capability);
            });
        }
    }
}
