<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Ratings\Contracts\TransferParticipantLookup;
use RowBuddy\Ratings\ValueObjects\TransferParticipantSnapshot;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * Bridges Ratings' read-only {@see TransferParticipantLookup} port to
 * Transfers' {@see TransferRepository} — the one place allowed to know
 * both packages' internals, per the composition-root pattern every prior
 * cross-module read port in this codebase already uses (mirrors
 * {@see EloquentTransferCaseLookup} exactly, adapted to Ratings' narrower
 * needs).
 */
final class EloquentTransferParticipantLookup implements TransferParticipantLookup
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
            isConfirmed: $transfer->status() === TransferStatus::Confirmed,
        );
    }
}
