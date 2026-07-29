<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Closure;
use Illuminate\Support\Facades\DB;
use RowBuddy\Bids\Contracts\TransactionManager;
use RowBuddy\Payments\Contracts\TransactionManager as PaymentsTransactionManager;
use RowBuddy\Transfers\Contracts\TransactionManager as TransfersTransactionManager;

/**
 * The one real {@see TransactionManager} implementation (ADR-012 §1):
 * wraps Laravel's `DB::transaction()`. Every Eloquent model touched
 * inside the callback (AuctionModel, BidModel, PaymentIntentModel,
 * TransferModel) uses the same default connection, so no connection
 * needs to be threaded through explicitly.
 *
 * Also implements Payments' (ADR-019 §6) and Transfers' (ADR-020) own
 * copies of this contract — all three interfaces declare the identical
 * `run()` signature, so one class satisfies all of them without any
 * conflict, avoiding a second and third, behaviorally-identical class
 * purely to satisfy a different interface name.
 */
final class LaravelTransactionManager implements PaymentsTransactionManager, TransactionManager, TransfersTransactionManager
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
