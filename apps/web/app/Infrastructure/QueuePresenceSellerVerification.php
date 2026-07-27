<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\ValueObjects\ConfidenceTier;
use RowBuddy\Auctions\ValueObjects\PresenceVerificationSnapshot;
use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;

/**
 * Bridges Auctions' {@see SellerPresenceVerification} port (ADR-009) to
 * QueuePresence's own {@see PresenceSessionService} — this class is the
 * one place in the codebase allowed to depend on both packages at once,
 * because apps/web is the composition root, not either module itself (see
 * tests/Architecture/ModuleBoundaryTest.php). Neither package depends on
 * the other's internals; QueuePresence has no knowledge that Auctions, or
 * this adapter, exist.
 *
 * Returns null (fail closed, ADR-009 §3) both when the seller has no
 * PresenceSession at all for this queue, and when a session exists but no
 * confidence score has ever been computed for it yet — deliberately not
 * synthesizing an Unverified/0 snapshot with a fabricated computedAt for
 * a fact that never actually happened.
 */
final class QueuePresenceSellerVerification implements SellerPresenceVerification
{
    public function __construct(private readonly PresenceSessionService $presenceSessions) {}

    public function verificationFor(string $sellerId, string $queueId): ?PresenceVerificationSnapshot
    {
        $session = $this->presenceSessions->latestSessionFor($sellerId, $queueId);

        if ($session === null) {
            return null;
        }

        $score = $this->presenceSessions->currentConfidenceScore($session->id, $sellerId);

        if ($score === null) {
            return null;
        }

        return new PresenceVerificationSnapshot(
            ConfidenceTier::from($score->tier->value),
            $score->points,
            $score->computedAt,
            $session->status() === PresenceSessionStatus::Active,
            $this->presenceSessions->latestWithinGeofencePingAt($session->id),
        );
    }
}
