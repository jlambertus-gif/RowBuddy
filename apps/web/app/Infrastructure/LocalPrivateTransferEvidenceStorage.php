<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\Transfers\Contracts\TransferEvidenceStorage;
use RowBuddy\Transfers\Exceptions\EvidenceStorageFailed;

/**
 * The real {@see TransferEvidenceStorage} implementation (ADR-020 §4),
 * mirroring {@see LocalPrivateEvidenceStorage}'s exact mechanics: Laravel's
 * private local disk (`local`, config/filesystems.php — not `public`),
 * signed temporary URLs, no MinIO/S3 yet. Lives in apps/web rather than
 * inside packages/Transfers because Storage::temporaryUrl() needs a
 * booted Laravel container that the package's own standalone tests never
 * boot — same reasoning as EloquentTransferGeofenceLookup.
 */
final class LocalPrivateTransferEvidenceStorage implements TransferEvidenceStorage
{
    private const DISK = 'local';

    public function store(string $transferId, string $contents): string
    {
        $path = "transfer-evidence/{$transferId}/".Str::uuid().'.jpg';

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
