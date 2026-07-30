<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Contracts;

use Closure;

/**
 * Framework-agnostic transaction boundary, mirroring Bids', Payments',
 * and Transfers' own copies of this same contract — each module owns its
 * own rather than sharing one. The real implementation wraps Laravel's
 * `DB::transaction()`; callers must not publish domain events from
 * inside the callback — collect them and return them instead, publishing
 * only after `run()` returns.
 */
interface TransactionManager
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    public function run(Closure $work): mixed;
}
