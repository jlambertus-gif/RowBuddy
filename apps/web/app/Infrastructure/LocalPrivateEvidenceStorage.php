<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;

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

        Storage::disk(self::DISK)->put($path, $contents);

        return $path;
    }

    public function temporaryUrl(string $reference, DateTimeImmutable $expiresAt): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($reference, $expiresAt);
    }
}
