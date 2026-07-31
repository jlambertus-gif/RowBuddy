<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

/**
 * The closed set of administrative abilities Laravel Gates authorize
 * against (ADR-026 Architecture Refinements §1) — application code
 * authorizes a capability ("can this user do X"), never a role
 * ("is this user role Y"). New cases are added one at a time, as each
 * sprint introduces the capability it actually needs — this is
 * deliberately not pre-populated with capabilities no sprint has built
 * yet.
 */
enum AdminCapability: string
{
    case QueuesModerate = 'queues.moderate';
    case RestrictionsModerate = 'restrictions.moderate';
    case DisputesReview = 'disputes.review';
    case AuditView = 'audit.view';
}
