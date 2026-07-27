<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\Application\ConfidenceRecomputer;
use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;

/**
 * Domain-facing persistence port for recorded evidence photos.
 */
interface EvidencePhotoRepository
{
    public function record(EvidencePhotoRecord $photo): void;

    public function findById(string $id): ?EvidencePhotoRecord;

    /**
     * Whether at least one evidence photo has been recorded for this
     * session. Feeds ADR-008's ConfidenceScorer via
     * {@see ConfidenceRecomputer}.
     */
    public function hasAnyForSession(string $presenceSessionId): bool;
}
