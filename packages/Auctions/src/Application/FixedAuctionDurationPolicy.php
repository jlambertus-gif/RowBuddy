<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use RowBuddy\Auctions\Contracts\AuctionDurationPolicy;

/**
 * The MVP default (ADR-013 §1): a single fixed duration for every
 * auction, regardless of queue. Framework-agnostic — the actual duration
 * value lives in exactly one binding (AuctionsServiceProvider), not here
 * and not in AuctionService, so it can be swapped for a genuinely
 * metadata-driven policy later without touching this class or the
 * aggregate.
 */
final class FixedAuctionDurationPolicy implements AuctionDurationPolicy
{
    public function __construct(private readonly int $durationInSeconds) {}

    public function durationInSecondsFor(string $queueId): int
    {
        return $this->durationInSeconds;
    }
}
