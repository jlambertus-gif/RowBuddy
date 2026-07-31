<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

use DateTimeImmutable;

/**
 * A read-only view of a dispute for Administration's case/evidence/
 * history review (ADR-026 §3) — everything the review surface needs,
 * expressed entirely in primitives so this package never depends on
 * `packages/Disputes`' own `Dispute` aggregate or enums (`DisputeStatus`,
 * `DisputeResolutionOutcome`). `$refundAmountMinorUnits`/
 * `$refundAmountCurrency` mirror `Dispute::refundAmount()`'s own
 * decomposition rather than reconstructing a `Money` value object here.
 */
final class DisputeCaseSnapshot
{
    /**
     * @param  list<DisputeCaseEvidenceSnapshot>  $evidence
     */
    public function __construct(
        public readonly string $id,
        public readonly string $transferId,
        public readonly string $auctionId,
        public readonly string $buyerId,
        public readonly string $sellerId,
        public readonly string $reason,
        public readonly DateTimeImmutable $openedAt,
        public readonly string $status,
        public readonly ?string $resolutionOutcome,
        public readonly ?int $refundAmountMinorUnits,
        public readonly ?string $refundAmountCurrency,
        public readonly ?string $resolvedBy,
        public readonly ?string $resolutionNotes,
        public readonly bool $evidenceFoundFraudulent,
        public readonly ?DateTimeImmutable $resolvedAt,
        public readonly array $evidence,
    ) {}
}
