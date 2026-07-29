<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Exceptions\TransferAlreadyIssuedForAuction;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceRecord;

final class InMemoryTransferRepository implements TransferRepository
{
    /** @var array<string, Transfer> */
    public array $saved = [];

    public function save(Transfer $transfer): void
    {
        foreach ($this->saved as $existing) {
            if ($existing->auctionId === $transfer->auctionId && $existing->id !== $transfer->id) {
                throw TransferAlreadyIssuedForAuction::forAuctionId($transfer->auctionId);
            }
        }

        $this->saved[$transfer->id] = $transfer;
    }

    public function recordEvidence(string $transferId, TransferEvidenceRecord $record): void
    {
        // Not exercised by any Sprint 4 test — evidence attachment isn't
        // part of the confirmation flow itself (ADR-020 §4).
    }

    public function findById(string $id): ?Transfer
    {
        return $this->saved[$id] ?? null;
    }

    public function findByAuctionId(string $auctionId): ?Transfer
    {
        foreach ($this->saved as $transfer) {
            if ($transfer->auctionId === $auctionId) {
                return $transfer;
            }
        }

        return null;
    }

    public function findByIdForUpdate(string $id): ?Transfer
    {
        return $this->findById($id);
    }
}
