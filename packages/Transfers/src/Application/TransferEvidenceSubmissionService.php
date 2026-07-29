<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;
use RowBuddy\Transfers\Contracts\ImageMetadataStripper;
use RowBuddy\Transfers\Contracts\TransferEvidenceStorage;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;

/**
 * Lets either party attach optional, repeatable photo evidence to a
 * `Transfer` (ADR-020 §4) — orthogonal to the confirmation state machine,
 * so deliberately not gated by status and not run inside a lock: evidence
 * is an append-only concern (mirroring `TransferRepository::recordEvidence()`'s
 * own docblock), never a mutation of the transfer's current state fields.
 *
 * Strips metadata before storing, exactly like
 * `QueuePresence\PresenceSessionService` does for presence evidence — the
 * stripped bytes are what actually gets persisted, never the original
 * upload.
 */
final class TransferEvidenceSubmissionService
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly TransferEvidenceStorage $storage,
        private readonly ImageMetadataStripper $metadataStripper,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws NotFoundException
     */
    public function submitPhoto(string $transferId, string $submittedBy, string $imageContents): void
    {
        $transfer = $this->transfers->findById($transferId);

        if ($transfer === null) {
            throw new NotFoundException("Transfer [{$transferId}] not found.");
        }

        $strippedContents = $this->metadataStripper->strip($imageContents);
        $storageReference = $this->storage->store($transferId, $strippedContents);

        $transfer->attachEvidence(TransferEvidenceType::Photo, $storageReference, $submittedBy, $this->clock);

        $records = $transfer->evidenceRecords();
        $this->transfers->recordEvidence($transferId, $records[array_key_last($records)]);

        foreach ($transfer->releaseEvents() as $event) {
            $this->events->publish($event);
        }
    }
}
