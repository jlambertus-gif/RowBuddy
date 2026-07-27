<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Contracts;

use RowBuddy\Auctions\ValueObjects\PresenceVerificationSnapshot;

/**
 * Domain-facing read port (ADR-009): lets Auctions check a seller's
 * presence/confidence state for a queue without depending on
 * QueuePresence's Eloquent models, its PresenceSessionService concrete
 * class, or any other QueuePresence internal. Owned by Auctions, in
 * Auctions' own vocabulary — implemented by an apps/web composition-root
 * adapter (ADR-009 §1).
 */
interface SellerPresenceVerification
{
    /**
     * Null means no PresenceSession exists at all for this seller and
     * queue — callers must fail closed (ADR-009 §3), never treat a null
     * result as verified.
     */
    public function verificationFor(string $sellerId, string $queueId): ?PresenceVerificationSnapshot;
}
