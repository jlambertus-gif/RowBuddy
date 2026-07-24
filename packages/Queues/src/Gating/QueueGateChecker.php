<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Gating;

use DateTimeImmutable;
use RowBuddy\Queues\Contracts\JurisdictionRuleRepository;
use RowBuddy\Queues\Contracts\RestrictedCategoryRepository;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;

/**
 * The restricted-category + jurisdiction-rule gate from ADR-005, shared by
 * every application service that creates or transitions a queue
 * (QueueSubmissionService at creation time, QueueModerationService at
 * approval time — "must run at approval time, not only at auction-creation
 * time"). Extracted rather than duplicated so the two call sites can never
 * silently drift apart.
 */
final class QueueGateChecker
{
    public function __construct(
        private readonly RestrictedCategoryRepository $restrictedCategories,
        private readonly JurisdictionRuleRepository $jurisdictionRules,
        private readonly JurisdictionGate $jurisdictionGate,
    ) {}

    /**
     * @throws QueueSubmissionBlocked
     */
    public function assertNotBlocked(string $category, string $jurisdictionCountry, DateTimeImmutable $asOf): void
    {
        if ($this->restrictedCategories->isCategoryRestricted($category, $jurisdictionCountry)) {
            throw QueueSubmissionBlocked::restrictedCategory($category, $jurisdictionCountry);
        }

        $rules = $this->jurisdictionRules->findForCountry($jurisdictionCountry);

        if (! $this->jurisdictionGate->isPermitted($rules, $category, $asOf)) {
            throw QueueSubmissionBlocked::jurisdictionNotPermitted($category, $jurisdictionCountry);
        }
    }
}
