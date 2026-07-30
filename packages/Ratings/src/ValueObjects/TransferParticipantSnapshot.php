<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\ValueObjects;

/**
 * Everything `packages/Ratings` needs to read from a `Transfer` for
 * submission eligibility (ADR-024 §2: `Confirmed`-only) and authorization
 * (ADR-024 §1: only the transfer's own buyer or seller may rate, and only
 * the other party) — a stable, Ratings-owned snapshot, never the
 * `Transfer` aggregate itself. Deliberately narrower than Disputes'
 * `TransferCaseSnapshot`: no evidence, no `confirmedAt` — Ratings has no
 * deadline computed from a transfer-side timestamp (the reveal deadline,
 * ADR-024 §5, is computed from the rating's own `submittedAt` instead).
 */
final class TransferParticipantSnapshot
{
    public function __construct(
        public readonly string $transferId,
        public readonly string $buyerId,
        public readonly string $sellerId,
        public readonly bool $isConfirmed,
    ) {}
}
