<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Notifications\Contracts\TransferParticipantLookup;
use RowBuddy\Notifications\ValueObjects\TransferParticipantSnapshot;
use RowBuddy\Transfers\Contracts\TransferRepository;

/**
 * Bridges Notifications' read-only {@see TransferParticipantLookup} port
 * to Transfers' {@see TransferRepository} — the one place allowed to
 * know both packages' internals, per the composition-root pattern every
 * prior cross-module read port in this codebase already uses. Named
 * distinctly from {@see EloquentTransferParticipantLookup} (Ratings'
 * identically-shaped but independent port of the same name) since both
 * classes live in this same `App\Infrastructure` namespace.
 */
final class EloquentNotificationTransferParticipantLookup implements TransferParticipantLookup
{
    public function __construct(
        private readonly TransferRepository $transfers,
    ) {}

    public function findByTransferId(string $transferId): ?TransferParticipantSnapshot
    {
        $transfer = $this->transfers->findById($transferId);

        if ($transfer === null) {
            return null;
        }

        return new TransferParticipantSnapshot(
            transferId: $transfer->id,
            buyerId: $transfer->buyerId,
            sellerId: $transfer->sellerId,
        );
    }
}
