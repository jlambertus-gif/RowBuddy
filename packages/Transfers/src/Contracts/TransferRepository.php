<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceRecord;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept — implementations translate between whatever storage
 * technology backs them and the {@see Transfer} aggregate, never the
 * reverse.
 *
 * `save()` persists only the transfer's own scalar/status fields — it
 * never touches evidence. Evidence is a separate, append-only concern
 * (ADR-020 §4): whichever application service calls
 * `Transfer::attachEvidence()` is responsible for also calling
 * `recordEvidence()` for each newly attached record in the same
 * operation. This keeps the mutable "current state" persistence
 * (mirroring `AuctionRepository::save()`) separate from the append-only
 * evidence log (mirroring `BidRepository::record()`), rather than
 * forcing one method to reconcile both.
 */
interface TransferRepository
{
    public function save(Transfer $transfer): void;

    public function recordEvidence(string $transferId, TransferEvidenceRecord $record): void;

    public function findById(string $id): ?Transfer;

    public function findByAuctionId(string $auctionId): ?Transfer;

    /**
     * Locks the transfer row for the duration of the caller's
     * transaction (`SELECT ... FOR UPDATE`) — the serialization anchor
     * both the lazy evaluator and the scheduled sweep rely on (ADR-018
     * §3). Callers must already be inside a transaction; this method
     * does not open one itself.
     */
    public function findByIdForUpdate(string $id): ?Transfer;
}
