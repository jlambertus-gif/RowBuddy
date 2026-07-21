<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Contracts;

use DateTimeImmutable;

/**
 * Every module reads "now" through this contract instead of calling
 * `new DateTimeImmutable()` directly, so time can be frozen/controlled in
 * tests (auction state machines, transfer/authorization expiry windows,
 * and confidence-score recency all depend on time).
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
