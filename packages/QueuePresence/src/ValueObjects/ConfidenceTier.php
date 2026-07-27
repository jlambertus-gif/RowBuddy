<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

/**
 * The five canonical confidence tiers from
 * docs/product/verification-model.md. v1's {@see
 * \RowBuddy\QueuePresence\Scoring\ConfidenceScorer} (ADR-008) can only ever
 * produce the first three — CommunityVerified and TransferCompleted depend
 * on capabilities (community confirmation, Transfers) that don't exist
 * yet. The full vocabulary is declared now so later phases extend the
 * scorer, not this enum.
 */
enum ConfidenceTier: string
{
    case Unverified = 'unverified';
    case LocationVerified = 'location_verified';
    case EvidenceVerified = 'evidence_verified';
    case CommunityVerified = 'community_verified';
    case TransferCompleted = 'transfer_completed';
}
