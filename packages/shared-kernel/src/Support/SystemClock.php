<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Support;

use DateTimeImmutable;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * The real-world clock, bound to {@see ClockInterface} in application
 * containers. Tests bind {@see FrozenClock} instead.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
