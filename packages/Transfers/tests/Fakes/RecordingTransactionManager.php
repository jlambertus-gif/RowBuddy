<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use Closure;
use RowBuddy\Transfers\Contracts\TransactionManager;

/**
 * A passthrough transaction manager (no real DB transaction — package
 * tests never boot Eloquent for these application services' own
 * suites), mirroring Bids' and Payments' own copies of this same fake.
 */
final class RecordingTransactionManager implements TransactionManager
{
    public function run(Closure $work): mixed
    {
        return $work();
    }
}
