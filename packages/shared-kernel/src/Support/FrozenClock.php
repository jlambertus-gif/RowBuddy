<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Support;

use DateTimeImmutable;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * A clock fixed to a single instant, for deterministic tests of anything
 * time-sensitive (auction closing, authorization-window expiry, transfer
 * windows, confidence-score recency).
 */
final class FrozenClock implements ClockInterface
{
    private readonly DateTimeImmutable $frozenAt;

    public function __construct(?DateTimeImmutable $frozenAt = null)
    {
        $this->frozenAt = $frozenAt ?? new DateTimeImmutable;
    }

    public function now(): DateTimeImmutable
    {
        return $this->frozenAt;
    }
}
