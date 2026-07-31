<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Application;

use RowBuddy\Administration\Contracts\AccountStandingRepository;
use RowBuddy\Administration\Contracts\AdminActionLog;
use RowBuddy\Administration\Exceptions\AccountAlreadyActive;
use RowBuddy\Administration\Exceptions\AccountAlreadySuspended;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\ValueObjects\AccountStandingState;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

/**
 * Orchestrates the sole account-standing consequence Phase 8 implements
 * (ADR-026 §4): a manual, admin-triggered, reversible suspension.
 * Suspension and reinstatement are each their own explicit action —
 * never automated, never triggered by any score, rule, or system
 * process (this service has no caller anywhere but an administrator's
 * own explicit request).
 *
 * Suspending an already-suspended account, or reinstating an already-
 * active one, is a hard rejection, not an idempotent no-op — mirroring
 * `DisputeFilingService`'s "a rejected attempt leaves no trace"
 * discipline. Every transition records exactly one {@see AdminActionLog}
 * entry, always after the standing itself is updated, carrying the
 * mandatory reason and the explicit previous/new
 * {@see AccountStandingState} values.
 */
final class AccountSuspensionService
{
    public function __construct(
        private readonly AccountStandingRepository $standings,
        private readonly AdminActionLog $actions,
    ) {}

    /**
     * @throws AccountAlreadySuspended
     * @throws AdministrativeActionReasonRequired
     */
    public function suspend(string $targetUserId, string $adminId, string $reason): void
    {
        $this->assertReasonNotBlank($reason);

        $current = $this->standings->findStanding($targetUserId);

        if ($current === AccountStandingState::Suspended) {
            throw AccountAlreadySuspended::forUser($targetUserId);
        }

        $this->standings->setStanding($targetUserId, AccountStandingState::Suspended);

        $this->actions->record(
            AdministrativeActionType::AccountSuspended,
            $adminId,
            'user',
            $targetUserId,
            $reason,
            $current,
            AccountStandingState::Suspended,
        );
    }

    /**
     * @throws AccountAlreadyActive
     * @throws AdministrativeActionReasonRequired
     */
    public function reinstate(string $targetUserId, string $adminId, string $reason): void
    {
        $this->assertReasonNotBlank($reason);

        $current = $this->standings->findStanding($targetUserId);

        if ($current === AccountStandingState::Active) {
            throw AccountAlreadyActive::forUser($targetUserId);
        }

        $this->standings->setStanding($targetUserId, AccountStandingState::Active);

        $this->actions->record(
            AdministrativeActionType::AccountReinstated,
            $adminId,
            'user',
            $targetUserId,
            $reason,
            $current,
            AccountStandingState::Active,
        );
    }

    /**
     * @throws AdministrativeActionReasonRequired
     */
    private function assertReasonNotBlank(string $reason): void
    {
        if (trim($reason) === '') {
            throw AdministrativeActionReasonRequired::create();
        }
    }
}
