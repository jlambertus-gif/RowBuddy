<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\Transfers\Contracts\TransferWindowPolicy;

/**
 * The MVP default (ADR-018 §1): a single fixed window for every
 * transfer, regardless of auction. Framework-agnostic — the actual
 * duration value lives in exactly one binding (TransfersServiceProvider),
 * not here, so it can be swapped for a genuinely metadata-driven policy
 * later without touching this class or the aggregate.
 *
 * Provisional MVP default: 24 hours — a placeholder configuration value,
 * not a permanent domain invariant, the same posture ADR-013 took for
 * the 30-minute auction-duration default.
 */
final class FixedTransferWindowPolicy implements TransferWindowPolicy
{
    public function __construct(private readonly int $durationInSeconds) {}

    public function durationInSecondsFor(string $auctionId): int
    {
        return $this->durationInSeconds;
    }
}
