<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use DateTimeImmutable;

/**
 * Domain-facing port for storing and retrieving evidence photo bytes.
 * Deliberately storage-technology-agnostic: Phase 2 binds this to
 * Laravel's private local disk (the approved storage decision), but
 * nothing in this contract assumes a local filesystem — an S3-compatible
 * adapter can be substituted later without touching the domain or
 * application layer.
 */
interface EvidenceStorage
{
    /**
     * Stores already-validated, already metadata-stripped image bytes
     * privately and returns an opaque reference — never a public URL.
     */
    public function store(string $presenceSessionId, string $contents): string;

    /**
     * A short-lived signed URL for viewing the stored photo. Never a
     * permanent or public link.
     */
    public function temporaryUrl(string $reference, DateTimeImmutable $expiresAt): string;
}
