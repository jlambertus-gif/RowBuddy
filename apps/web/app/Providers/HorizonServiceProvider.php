<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use RowBuddy\Administration\ValueObjects\AdminCapability;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local
     * environments (ADR-027 Decision 5) — delegated entirely to the
     * `horizon.view` capability `AdministrationServiceProvider::boot()`
     * already registers generically for every `AdminCapability` case, so
     * this stays a thin delegation rather than a second authorization
     * model. Horizon's dashboard surfaces job payloads across every
     * queue in the system — a strictly broader view than the audit log's
     * own allowlisted one — so this is `Administrator`-only, per
     * `AdminRoleCapabilityMap`.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?Authenticatable $user = null): bool {
            if ($user === null) {
                return false;
            }

            return Gate::forUser($user)->allows(AdminCapability::HorizonView->value);
        });
    }
}
