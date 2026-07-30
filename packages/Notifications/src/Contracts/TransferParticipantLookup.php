<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

use RowBuddy\Notifications\ValueObjects\TransferParticipantSnapshot;

/**
 * Notifications-owned read port into Transfers, extending the
 * "consumer owns the port" pattern — `TransferConfirmed`/
 * `TransferExpired`/`TransferCancelled` (ADR-025 §6) carry only
 * `transferId`, not the transfer's buyer/seller, so resolving their
 * recipients needs this one hop. Implemented by an `apps/web` adapter
 * bridging to `TransferRepository`. Deliberately independent of Ratings'
 * identically-shaped port of the same name — the two modules share no
 * dependency on each other.
 */
interface TransferParticipantLookup
{
    public function findByTransferId(string $transferId): ?TransferParticipantSnapshot;
}
