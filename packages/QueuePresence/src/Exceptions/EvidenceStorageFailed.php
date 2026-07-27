<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Exceptions;

use RowBuddy\QueuePresence\Contracts\EvidenceStorage;
use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when an {@see EvidenceStorage}
 * implementation fails to persist evidence bytes. Found via real-browser
 * acceptance testing (Sprint 7): Laravel's filesystem disks are
 * configured with `'throw' => false`, so a failed write returns `false`
 * instead of throwing — silently proceeding as if storage succeeded
 * would record an EvidencePhotoRecord (and count it toward the
 * confidence score) for a photo that was never actually persisted.
 */
final class EvidenceStorageFailed extends DomainException
{
    public static function forPath(string $path): self
    {
        return new self("Failed to store evidence photo at [{$path}].");
    }
}
