<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Tests\Fakes;

use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\Exceptions\DisputeAlreadyExistsForTransfer;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceRecord;

final class InMemoryDisputeRepository implements DisputeRepository
{
    /** @var array<string, Dispute> */
    public array $saved = [];

    /** @var array<string, list<DisputeEvidenceRecord>> */
    public array $recordedEvidence = [];

    public function save(Dispute $dispute): void
    {
        foreach ($this->saved as $existing) {
            if ($existing->transferId === $dispute->transferId && $existing->id !== $dispute->id) {
                throw DisputeAlreadyExistsForTransfer::forTransferId($dispute->transferId);
            }
        }

        $this->saved[$dispute->id] = $dispute;
    }

    public function recordEvidence(string $disputeId, DisputeEvidenceRecord $record): void
    {
        $this->recordedEvidence[$disputeId][] = $record;
    }

    public function findById(string $id): ?Dispute
    {
        return $this->saved[$id] ?? null;
    }

    public function findByTransferId(string $transferId): ?Dispute
    {
        foreach ($this->saved as $dispute) {
            if ($dispute->transferId === $transferId) {
                return $dispute;
            }
        }

        return null;
    }

    public function findByIdForUpdate(string $id): ?Dispute
    {
        return $this->findById($id);
    }

    public function findAll(): array
    {
        $disputes = array_values($this->saved);

        usort($disputes, static fn (Dispute $a, Dispute $b): int => $b->openedAt <=> $a->openedAt);

        return $disputes;
    }
}
