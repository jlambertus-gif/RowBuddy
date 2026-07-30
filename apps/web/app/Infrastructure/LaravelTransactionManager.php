<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Closure;
use Illuminate\Support\Facades\DB;
use RowBuddy\Bids\Contracts\TransactionManager;
use RowBuddy\Disputes\Contracts\TransactionManager as DisputesTransactionManager;
use RowBuddy\Payments\Contracts\TransactionManager as PaymentsTransactionManager;
use RowBuddy\Transfers\Contracts\TransactionManager as TransfersTransactionManager;

/**
 * The one real {@see TransactionManager} implementation (ADR-012 §1):
 * wraps Laravel's `DB::transaction()`. Every Eloquent model touched
 * inside the callback (AuctionModel, BidModel, PaymentIntentModel,
 * TransferModel, DisputeModel) uses the same default connection, so no
 * connection needs to be threaded through explicitly.
 *
 * Also implements Payments' (ADR-019 §6), Transfers' (ADR-020), and
 * Disputes' (Phase 6) own copies of this contract — all four interfaces
 * declare the identical `run()` signature, so one class satisfies all of
 * them without any conflict, avoiding further behaviorally-identical
 * classes purely to satisfy a different interface name.
 */
final class LaravelTransactionManager implements DisputesTransactionManager, PaymentsTransactionManager, TransactionManager, TransfersTransactionManager
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    public function run(Closure $work): mixed
    {
        return DB::transaction($work);
    }
}
