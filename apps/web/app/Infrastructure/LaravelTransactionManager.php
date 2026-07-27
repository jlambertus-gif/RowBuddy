<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Closure;
use Illuminate\Support\Facades\DB;
use RowBuddy\Bids\Contracts\TransactionManager;

/**
 * The one real {@see TransactionManager} implementation (ADR-012 §1):
 * wraps Laravel's `DB::transaction()`. Every Eloquent model touched
 * inside the callback (AuctionModel, BidModel) uses the same default
 * connection, so no connection needs to be threaded through explicitly.
 */
final class LaravelTransactionManager implements TransactionManager
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
