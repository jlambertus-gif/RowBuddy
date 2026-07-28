<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use RowBuddy\Auctions\Contracts\AntiSnipingPolicy;

/**
 * The MVP default (ADR-013 §2): fixed soft-close window and extension
 * durations. Provisional configuration values, not permanent invariants —
 * see AuctionsServiceProvider for where the actual numbers are bound.
 */
final class FixedAntiSnipingPolicy implements AntiSnipingPolicy
{
    public function __construct(
        private readonly int $softCloseWindowInSeconds,
        private readonly int $extensionInSeconds,
    ) {}

    public function softCloseWindowInSeconds(): int
    {
        return $this->softCloseWindowInSeconds;
    }

    public function extensionInSeconds(): int
    {
        return $this->extensionInSeconds;
    }
}
