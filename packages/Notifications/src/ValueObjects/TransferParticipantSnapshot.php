<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\ValueObjects;

/**
 * Everything `packages/Notifications` needs to read from a `Transfer` to
 * resolve the recipients of `TransferConfirmed`/`TransferExpired`/
 * `TransferCancelled` (ADR-025 §6) — those events do not carry
 * `buyerId`/`sellerId` directly. This is Notifications' own independent
 * copy of the same shape Ratings' `TransferParticipantSnapshot` already
 * uses (ADR-024 §2/§7) — the two modules share no dependency on each
 * other; each owns its own read port into Transfers, per this
 * codebase's standing "consumer owns the port" discipline.
 */
final class TransferParticipantSnapshot
{
    public function __construct(
        public readonly string $transferId,
        public readonly string $buyerId,
        public readonly string $sellerId,
    ) {}
}
