<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

use DateTimeImmutable;

/**
 * A short-lived, signed URL for viewing a private evidence photo — never
 * a permanent or public link, per "protect evidence with signed or
 * authenticated URLs".
 */
final class TemporaryEvidenceUrl
{
    public function __construct(
        public readonly string $url,
        public readonly DateTimeImmutable $expiresAt,
    ) {}
}
