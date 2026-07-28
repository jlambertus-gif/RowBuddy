<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Contracts;

/**
 * The soft-close (anti-sniping) configuration (ADR-013 §2): a bid
 * accepted within `softCloseWindowInSeconds()` of the current `closesAt`
 * pushes the deadline forward by `extensionInSeconds()`, calculated from
 * the current `closesAt`, never from the bid's own timestamp.
 */
interface AntiSnipingPolicy
{
    public function softCloseWindowInSeconds(): int;

    public function extensionInSeconds(): int;
}
