<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use Closure;
use RowBuddy\Payments\Contracts\TransactionManager;

/**
 * A passthrough transaction manager (no real DB transaction — package
 * tests never boot Eloquent for PaymentCaptureService's own suite),
 * mirroring Bids' own copy of this same fake.
 */
final class RecordingTransactionManager implements TransactionManager
{
    public function run(Closure $work): mixed
    {
        return $work();
    }
}
