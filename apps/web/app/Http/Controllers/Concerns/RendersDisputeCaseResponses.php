<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use RowBuddy\Administration\ValueObjects\DisputeCaseEvidenceSnapshot;
use RowBuddy\Administration\ValueObjects\DisputeCaseSnapshot;

/**
 * Shared DisputeCaseSnapshot -> JSON shape for the dispute-review
 * controllers — no business logic, just serialization.
 */
trait RendersDisputeCaseResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(DisputeCaseSnapshot $dispute): array
    {
        return [
            'id' => $dispute->id,
            'transfer_id' => $dispute->transferId,
            'auction_id' => $dispute->auctionId,
            'buyer_id' => $dispute->buyerId,
            'seller_id' => $dispute->sellerId,
            'reason' => $dispute->reason,
            'opened_at' => $dispute->openedAt->format(DATE_ATOM),
            'status' => $dispute->status,
            'resolution_outcome' => $dispute->resolutionOutcome,
            'refund_amount_minor_units' => $dispute->refundAmountMinorUnits,
            'refund_amount_currency' => $dispute->refundAmountCurrency,
            'resolved_by' => $dispute->resolvedBy,
            'resolution_notes' => $dispute->resolutionNotes,
            'evidence_found_fraudulent' => $dispute->evidenceFoundFraudulent,
            'resolved_at' => $dispute->resolvedAt?->format(DATE_ATOM),
            'evidence' => array_map($this->toEvidenceResponse(...), $dispute->evidence),
        ];
    }

    /**
     * `storageReference` overloads two different meanings on
     * DisputeEvidenceRecord: for `written_statement` it *is* the
     * evidence's actual text content (safe and necessary to display for
     * review); for `photo` it is a private storage path, which must
     * never reach the client raw (ADR-026 Architecture Refinements §7 —
     * dedicated evidence access, if ever built, is its own deliberate
     * design, never a side effect of generic review). Photo evidence
     * viewing is therefore out of scope for Sprint 4: only its metadata
     * (that a photo was submitted, by whom, when) is exposed.
     *
     * @return array<string, mixed>
     */
    private function toEvidenceResponse(DisputeCaseEvidenceSnapshot $evidence): array
    {
        return [
            'type' => $evidence->type,
            'content' => $evidence->type === 'written_statement' ? $evidence->storageReference : null,
            'submitted_by' => $evidence->submittedBy,
            'submitted_at' => $evidence->submittedAt->format(DATE_ATOM),
        ];
    }
}
