<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;
use RowBuddy\Transfers\Contracts\TransferEvidenceStorage;

/**
 * Raised when a {@see TransferEvidenceStorage}
 * implementation fails to persist evidence bytes. Mirrors QueuePresence's
 * own `EvidenceStorageFailed` (Phase 2): Laravel's filesystem disks are
 * configured with `'throw' => false`, so a failed write returns `false`
 * instead of throwing — silently proceeding as if storage succeeded would
 * attach a `TransferEvidenceRecord` for a photo that was never actually
 * persisted.
 */
final class EvidenceStorageFailed extends DomainException
{
    public static function forPath(string $path): self
    {
        return new self("Failed to store transfer evidence photo at [{$path}].");
    }
}
