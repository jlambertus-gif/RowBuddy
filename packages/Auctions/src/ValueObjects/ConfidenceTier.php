<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\ValueObjects;

/**
 * Auctions' own copy of QueuePresence's five canonical confidence tiers
 * (ADR-008), deliberately not a reuse of QueuePresence's enum — ADR-009 §2
 * requires the exposed DTO to use Auctions-owned vocabulary. The
 * `apps/web` SellerPresenceVerification adapter maps QueuePresence's tier
 * onto this one by matching string value; this package has no dependency
 * on QueuePresence.
 */
enum ConfidenceTier: string
{
    case Unverified = 'unverified';
    case LocationVerified = 'location_verified';
    case EvidenceVerified = 'evidence_verified';
    case CommunityVerified = 'community_verified';
    case TransferCompleted = 'transfer_completed';
}
