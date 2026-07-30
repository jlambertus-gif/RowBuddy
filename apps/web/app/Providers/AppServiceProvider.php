<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\EloquentAuctionGateway;
use App\Infrastructure\EloquentPaymentCaptureGateway;
use App\Infrastructure\EloquentPaymentRefundGateway;
use App\Infrastructure\EloquentQueueGeofenceLookup;
use App\Infrastructure\EloquentRecipientContactLookup;
use App\Infrastructure\EloquentRecipientLocalePreferenceLookup;
use App\Infrastructure\EloquentTransferCaseLookup;
use App\Infrastructure\EloquentTransferGeofenceLookup;
use App\Infrastructure\EloquentTransferParticipantLookup;
use App\Infrastructure\EloquentWinningBidderLookup;
use App\Infrastructure\EloquentWinningBidLookup;
use App\Infrastructure\LaravelTransactionManager;
use App\Infrastructure\LocalPrivateEvidenceStorage;
use App\Infrastructure\LocalPrivateTransferEvidenceStorage;
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
use RowBuddy\Disputes\Contracts\PaymentRefundGateway;
use RowBuddy\Disputes\Contracts\TransactionManager as DisputesTransactionManager;
use RowBuddy\Disputes\Contracts\TransferCaseLookup;
use RowBuddy\Notifications\Contracts\RecipientContactLookup;
use RowBuddy\Notifications\Contracts\RecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Contracts\WinningBidderLookup;
use RowBuddy\Payments\Contracts\TransactionManager as PaymentsTransactionManager;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;
use RowBuddy\QueuePresence\Contracts\QueueGeofenceLookup;
use RowBuddy\Ratings\Contracts\TransferParticipantLookup;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Support\SystemClock;
use RowBuddy\Transfers\Contracts\PaymentCaptureGateway;
use RowBuddy\Transfers\Contracts\TransactionManager as TransfersTransactionManager;
use RowBuddy\Transfers\Contracts\TransferEvidenceStorage;
use RowBuddy\Transfers\Contracts\TransferGeofenceLookup;

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

        // Transfers' own copy of the transaction-manager contract
        // (ADR-020) — the same LaravelTransactionManager class satisfies
        // all three modules' copies of this contract.
        $this->app->bind(TransfersTransactionManager::class, LaravelTransactionManager::class);

        // Bridges Transfers -> Auctions + Queues (ADR-020 §1; see
        // EloquentTransferGeofenceLookup's own docblock): a cross-module
        // concern, so it's bound at the composition root, not inside any
        // one module's own provider.
        $this->app->bind(TransferGeofenceLookup::class, EloquentTransferGeofenceLookup::class);

        // Bridges Transfers -> Payments (ADR-019 §4; see
        // EloquentPaymentCaptureGateway's own docblock) — the
        // Transfers-to-Payments capture contract.
        $this->app->bind(PaymentCaptureGateway::class, EloquentPaymentCaptureGateway::class);

        // Needs a booted Laravel container (Storage facade, signed-route
        // URL generation) that packages/Transfers' own standalone tests
        // never boot — see LocalPrivateTransferEvidenceStorage's docblock.
        $this->app->bind(TransferEvidenceStorage::class, LocalPrivateTransferEvidenceStorage::class);

        // Bridges Disputes -> Transfers (Phase 6 architecture review
        // §4/§13; see EloquentTransferCaseLookup's own docblock): a
        // cross-module concern, so it's bound at the composition root,
        // not inside either module's own provider.
        $this->app->bind(TransferCaseLookup::class, EloquentTransferCaseLookup::class);

        // Disputes' own copy of the transaction-manager contract (Phase
        // 6) — the same LaravelTransactionManager class satisfies all
        // four modules' copies of this contract.
        $this->app->bind(DisputesTransactionManager::class, LaravelTransactionManager::class);

        // Bridges Disputes -> Payments (ADR-022; see
        // EloquentPaymentRefundGateway's own docblock) — the
        // Disputes-to-Payments refund contract.
        $this->app->bind(PaymentRefundGateway::class, EloquentPaymentRefundGateway::class);

        // Bridges Ratings -> Transfers (ADR-024 §2/§7; see
        // EloquentTransferParticipantLookup's own docblock): a
        // cross-module concern, so it's bound at the composition root,
        // not inside either module's own provider.
        $this->app->bind(TransferParticipantLookup::class, EloquentTransferParticipantLookup::class);

        // Bridges Notifications -> Identity's `users` table (Phase 7
        // Notifications sprint plan; see
        // EloquentRecipientLocalePreferenceLookup's/
        // EloquentRecipientContactLookup's own docblocks): a cross-module
        // concern, so bound at the composition root, not inside
        // Notifications' own provider. packages/Notifications never reads
        // the User model directly.
        $this->app->bind(RecipientLocalePreferenceLookup::class, EloquentRecipientLocalePreferenceLookup::class);
        $this->app->bind(RecipientContactLookup::class, EloquentRecipientContactLookup::class);

        // Bridges Notifications -> Bids (see
        // EloquentWinningBidderLookup's own docblock): `AuctionWon`
        // carries `winningBidId`, not the winning bidder's own id, so
        // resolving the AuctionWon notification's real recipient needs
        // this one extra hop.
        $this->app->bind(WinningBidderLookup::class, EloquentWinningBidderLookup::class);
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
