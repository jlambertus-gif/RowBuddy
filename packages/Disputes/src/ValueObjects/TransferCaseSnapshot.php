<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\ValueObjects;

use DateTimeImmutable;

/**
 * Everything `packages/Disputes` needs to read from a `Transfer` for
 * filing eligibility (ADR-021 §2: `Confirmed`-only), deadline
 * calculation (ADR-021 §3, from `confirmedAt`), and case review
 * (aggregated, read-only evidence, ADR-021 §6/Phase 6 architecture
 * review §4/§13) — a stable, Disputes-owned snapshot, never the
 * `Transfer` aggregate itself.
 */
final class TransferCaseSnapshot
{
    /**
     * @param  list<TransferEvidenceSummary>  $evidenceRecords
     */
    public function __construct(
        public readonly string $transferId,
        public readonly string $auctionId,
        public readonly string $buyerId,
        public readonly string $sellerId,
        public readonly bool $isConfirmed,
        public readonly ?DateTimeImmutable $confirmedAt,
        public readonly array $evidenceRecords,
    ) {}
}
