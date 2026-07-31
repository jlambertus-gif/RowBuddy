<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Administration\Contracts\DisputeCaseLookup;
use RowBuddy\Administration\ValueObjects\DisputeCaseEvidenceSnapshot;
use RowBuddy\Administration\ValueObjects\DisputeCaseSnapshot;
use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceRecord;

/**
 * Bridges Administration's read-only {@see DisputeCaseLookup} port to
 * Disputes' own {@see DisputeRepository} — the one place allowed to
 * know both packages' internals, per the composition-root pattern every
 * prior cross-module read port in this codebase already uses (e.g.
 * EloquentTransferCaseLookup). `packages/Administration` never depends
 * on `packages/Disputes` directly.
 */
final class EloquentDisputeCaseLookup implements DisputeCaseLookup
{
    public function __construct(
        private readonly DisputeRepository $disputes,
    ) {}

    public function listAll(): array
    {
        return array_map($this->toSnapshot(...), $this->disputes->findAll());
    }

    public function findById(string $disputeId): ?DisputeCaseSnapshot
    {
        $dispute = $this->disputes->findById($disputeId);

        return $dispute === null ? null : $this->toSnapshot($dispute);
    }

    private function toSnapshot(Dispute $dispute): DisputeCaseSnapshot
    {
        $refundAmount = $dispute->refundAmount();

        return new DisputeCaseSnapshot(
            id: $dispute->id,
            transferId: $dispute->transferId,
            auctionId: $dispute->auctionId,
            buyerId: $dispute->buyerId,
            sellerId: $dispute->sellerId,
            reason: $dispute->reason,
            openedAt: $dispute->openedAt,
            status: $dispute->status()->value,
            resolutionOutcome: $dispute->resolutionOutcome()?->value,
            refundAmountMinorUnits: $refundAmount?->minorUnits,
            refundAmountCurrency: $refundAmount !== null ? (string) $refundAmount->currency : null,
            resolvedBy: $dispute->resolvedBy(),
            resolutionNotes: $dispute->resolutionNotes(),
            evidenceFoundFraudulent: $dispute->evidenceFoundFraudulent(),
            resolvedAt: $dispute->resolvedAt(),
            evidence: array_map($this->toEvidenceSnapshot(...), $dispute->evidenceRecords()),
        );
    }

    private function toEvidenceSnapshot(DisputeEvidenceRecord $record): DisputeCaseEvidenceSnapshot
    {
        return new DisputeCaseEvidenceSnapshot(
            $record->type->value,
            $record->storageReference,
            $record->submittedBy,
            $record->submittedAt,
        );
    }
}
