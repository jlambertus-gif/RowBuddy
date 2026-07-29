<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\EloquentAuctionGateway;
use App\Infrastructure\EloquentQueueGeofenceLookup;
use App\Infrastructure\EloquentWinningBidLookup;
use App\Infrastructure\LaravelTransactionManager;
use App\Infrastructure\LocalPrivateEvidenceStorage;
use App\Infrastructure\QueuePresenceSellerVerification;
use App\Listeners\RecordAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\Contracts\TransactionManager;
use RowBuddy\Payments\Contracts\TransactionManager as PaymentsTransactionManager;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;
use RowBuddy\QueuePresence\Contracts\QueueGeofenceLookup;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
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

        // Bridges QueuePresence -> Queues (see EloquentQueueGeofenceLookup's
        // own docblock): a cross-module concern, so it's bound at the
        // composition root, not inside either module's own provider.
        $this->app->bind(QueueGeofenceLookup::class, EloquentQueueGeofenceLookup::class);

        // Needs a booted Laravel container (Storage facade, signed-route
        // URL generation) that packages/QueuePresence's own standalone
        // tests never boot — see LocalPrivateEvidenceStorage's docblock.
        $this->app->bind(EvidenceStorage::class, LocalPrivateEvidenceStorage::class);

        // Bridges Auctions -> QueuePresence (ADR-009 §1; see
        // QueuePresenceSellerVerification's own docblock): a cross-module
        // concern, so it's bound at the composition root, not inside
        // either module's own provider.
        $this->app->bind(SellerPresenceVerification::class, QueuePresenceSellerVerification::class);

        // Bridges Bids -> Auctions (ADR-012 §5; see
        // EloquentAuctionGateway's own docblock): a cross-module concern,
        // so it's bound at the composition root, not inside either
        // module's own provider.
        $this->app->bind(AuctionGateway::class, EloquentAuctionGateway::class);

        // Wraps DB::transaction() — composition-root territory, not
        // something packages/Bids' own standalone tests ever boot.
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);

        // Payments' own copy of the same contract (ADR-019 §6) — the same
        // LaravelTransactionManager class satisfies both.
        $this->app->bind(PaymentsTransactionManager::class, LaravelTransactionManager::class);

        // Bridges Auctions -> Bids (ADR-013 §5; see
        // EloquentWinningBidLookup's own docblock) — the reverse direction
        // from AuctionGateway above, same composition-root reasoning.
        $this->app->bind(WinningBidLookup::class, EloquentWinningBidLookup::class);
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

        // The one, platform-wide audit sink (Sprint 6): registered
        // against the AuditableAction interface, not a concrete event
        // class, so it fires for every module's audit-worthy events —
        // Queues' and QueuePresence's alike — with no per-module wiring.
        Event::listen(AuditableAction::class, RecordAuditEvent::class);
    }
}
