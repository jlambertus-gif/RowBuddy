<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use Closure;
use RowBuddy\Bids\Contracts\TransactionManager;

/**
 * A passthrough transaction manager (no real DB transaction — package
 * tests never boot Eloquent for BidService's own suite) that also tracks
 * whether a callback is currently executing, so {@see RecordingDomainEventPublisher}
 * can detect — and fail loudly on — a publish() call made before `run()`
 * returns (ADR-012 §2's "no pre-commit event escapes" property).
 */
final class RecordingTransactionManager implements TransactionManager
{
    public bool $insideRun = false;

    public function run(Closure $work): mixed
    {
        $this->insideRun = true;

        try {
            return $work();
        } finally {
            $this->insideRun = false;
        }
    }
}
