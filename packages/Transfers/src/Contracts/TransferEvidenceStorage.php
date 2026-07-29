<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

use DateTimeImmutable;
use RowBuddy\Transfers\Exceptions\EvidenceStorageFailed;

/**
 * Domain-facing port for storing and retrieving handoff evidence photo
 * bytes (ADR-020 §4). Deliberately storage-technology-agnostic and
 * Transfers' own copy of QueuePresence's `EvidenceStorage` shape (ADR-020
 * §4: "reusing the same private-disk-plus-signed-URL-plus-EXIF-stripping
 * mechanics `LocalPrivateEvidenceStorage` already established in Phase
 * 2" — the mechanics, not the interface itself, per this project's
 * "each consuming module gets its own copy of this kind of port" rule).
 */
interface TransferEvidenceStorage
{
    /**
     * Stores already-validated, already metadata-stripped image bytes
     * privately and returns an opaque reference — never a public URL.
     *
     * @throws EvidenceStorageFailed if the write did not actually succeed
     */
    public function store(string $transferId, string $contents): string;

    /**
     * A short-lived signed URL for viewing the stored photo. Never a
     * permanent or public link.
     */
    public function temporaryUrl(string $reference, DateTimeImmutable $expiresAt): string;
}
