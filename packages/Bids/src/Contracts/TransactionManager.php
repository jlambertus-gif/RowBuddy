<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

use Closure;

/**
 * Framework-agnostic transaction boundary (ADR-012 §1). The real
 * implementation wraps Laravel's `DB::transaction()`; callers must not
 * publish domain events from inside the callback — collect them and
 * return them instead, publishing only after `run()` returns (ADR-012
 * §1a/§2).
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
