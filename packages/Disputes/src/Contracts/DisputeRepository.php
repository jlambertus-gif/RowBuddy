<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Contracts;

use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceRecord;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept — implementations translate between whatever storage
 * technology backs them and the {@see Dispute} aggregate, never the
 * reverse.
 *
 * `save()` persists only the dispute's own scalar/status fields — it
 * never touches evidence. Evidence is a separate, append-only concern
 * (ADR-021 §7): whichever application service calls
 * `Dispute::attachEvidence()` is responsible for also calling
 * `recordEvidence()` for the newly attached record in the same
 * operation. This mirrors `TransferRepository`'s identical split between
 * mutable "current state" persistence and the append-only evidence log.
 */
interface DisputeRepository
{
    public function save(Dispute $dispute): void;

    public function recordEvidence(string $disputeId, DisputeEvidenceRecord $record): void;

    public function findById(string $id): ?Dispute;

    public function findByTransferId(string $transferId): ?Dispute;

    /**
     * Locks the dispute row for the duration of the caller's transaction
     * (`SELECT ... FOR UPDATE`) — the serialization anchor a future
     * resolution application service will rely on, mirroring
     * `TransferRepository::findByIdForUpdate()`. Callers must already be
     * inside a transaction; this method does not open one itself.
     */
    public function findByIdForUpdate(string $id): ?Dispute;
}
