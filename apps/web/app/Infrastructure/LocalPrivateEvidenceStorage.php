<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;
use RowBuddy\QueuePresence\Exceptions\EvidenceStorageFailed;

/**
 * The one real {@see EvidenceStorage} implementation for Phase 2, per the
 * approved storage decision: Laravel's private local disk (`local`,
 * config/filesystems.php — not `public`), signed temporary URLs, no
 * MinIO/S3 yet. Lives in apps/web rather than inside
 * packages/QueuePresence because Storage::temporaryUrl() needs a booted
 * Laravel container (the signed "storage.local" route) that the
 * package's own standalone tests never boot — same reasoning as
 * EloquentQueueGeofenceLookup. Swapping to an S3-compatible disk later is
 * a one-line change to the `DISK` constant plus filesystems.php config,
 * with no change to the domain or application layer.
 */
final class LocalPrivateEvidenceStorage implements EvidenceStorage
{
    private const DISK = 'local';

    public function store(string $presenceSessionId, string $contents): string
    {
        $path = "presence-evidence/{$presenceSessionId}/".Str::uuid().'.jpg';

        // filesystems.php configures this disk with 'throw' => false, so a
        // failed write returns false here rather than throwing on its
        // own — checking the return value is the only thing standing
        // between "the write failed" and silently recording a photo that
        // was never actually persisted.
        if (! Storage::disk(self::DISK)->put($path, $contents)) {
            throw EvidenceStorageFailed::forPath($path);
        }

        return $path;
    }

    public function temporaryUrl(string $reference, DateTimeImmutable $expiresAt): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($reference, $expiresAt);
    }
}
