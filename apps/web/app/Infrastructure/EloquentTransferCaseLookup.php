<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Disputes\Contracts\TransferCaseLookup;
use RowBuddy\Disputes\ValueObjects\TransferCaseSnapshot;
use RowBuddy\Disputes\ValueObjects\TransferEvidenceSummary;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * Bridges Disputes' read-only {@see TransferCaseLookup} port to
 * Transfers' {@see TransferRepository} — the one place allowed to know
 * both packages' internals, per the composition-root pattern every prior
 * cross-module read port in this codebase already uses.
 */
final class EloquentTransferCaseLookup implements TransferCaseLookup
{
    public function __construct(
        private readonly TransferRepository $transfers,
    ) {}

    public function findByTransferId(string $transferId): ?TransferCaseSnapshot
    {
        $transfer = $this->transfers->findById($transferId);

        if ($transfer === null) {
            return null;
        }

        $evidenceRecords = [];

        foreach ($transfer->evidenceRecords() as $record) {
            $evidenceRecords[] = new TransferEvidenceSummary(
                $record->type->value,
                $record->storageReference,
                $record->submittedBy,
                $record->submittedAt,
            );
        }

        return new TransferCaseSnapshot(
            transferId: $transfer->id,
            auctionId: $transfer->auctionId,
            buyerId: $transfer->buyerId,
            sellerId: $transfer->sellerId,
            isConfirmed: $transfer->status() === TransferStatus::Confirmed,
            confirmedAt: $transfer->confirmedAt(),
            evidenceRecords: $evidenceRecords,
        );
    }
}
