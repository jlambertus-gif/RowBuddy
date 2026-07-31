<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Application;

use RowBuddy\Administration\Contracts\AdminActionLog;
use RowBuddy\Administration\Contracts\DisputeCaseLookup;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\Exceptions\AdministrativeTargetNotFound;
use RowBuddy\Administration\Infrastructure\AdministrationServiceProvider;
use RowBuddy\Administration\ValueObjects\AdminCapability;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

/**
 * Orchestrates the sole administrative action ADR-026 §3 grants over a
 * dispute: recording a correction note — a decision, note, or referral
 * an administrator wants on record after reviewing a case, never a
 * second resolution. `Dispute.Resolved` stays exactly as terminal as
 * Phase 6 built it (ADR-021 §5): this service never calls, reaches into,
 * or mutates the `Dispute` aggregate in any way, only
 * {@see DisputeCaseLookup} to confirm the target exists. No generic
 * financial-correction engine exists here either (Architecture
 * Refinements §5) — the correction is a plain, mandatory-reason note,
 * never a payment adjustment, refund, or balance mutation.
 *
 * Mirrors {@see AccountSuspensionService}'s (Sprint 2) and
 * {@see RestrictionActivationService}'s (Sprint 3) authorization
 * posture exactly: this service assumes the caller is already
 * authorized and enforces only business invariants (mandatory reason,
 * target existence). Capability authorization — the `disputes.review`
 * Gate {@see AdministrationServiceProvider}
 * already registers generically for every
 * {@see AdminCapability} case —
 * belongs at the application boundary, never inside this service.
 */
final class DisputeCorrectionService
{
    public function __construct(
        private readonly DisputeCaseLookup $disputes,
        private readonly AdminActionLog $actions,
    ) {}

    /**
     * @throws AdministrativeActionReasonRequired
     * @throws AdministrativeTargetNotFound
     */
    public function recordCorrection(string $disputeId, string $adminId, string $note): void
    {
        $this->assertReasonNotBlank($note);

        $dispute = $this->disputes->findById($disputeId);

        if ($dispute === null) {
            throw AdministrativeTargetNotFound::forTarget('dispute', $disputeId);
        }

        $this->actions->record(
            AdministrativeActionType::DisputeCorrectionRecorded,
            $adminId,
            'dispute',
            $disputeId,
            $note,
            null,
            null,
        );
    }

    /**
     * @throws AdministrativeActionReasonRequired
     */
    private function assertReasonNotBlank(string $note): void
    {
        if (trim($note) === '') {
            throw AdministrativeActionReasonRequired::create();
        }
    }
}
