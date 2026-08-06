<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\ValueObjects;

/**
 * A push notification's rendered content — deliberately just two plain
 * strings, mirroring the same field-restriction discipline ADR-025 §9
 * imposes on email (ADR-028 Decision 6): no raw provider identifiers, no
 * internal ids, no un-allowlisted content. Sourced from the exact same
 * per-type translation keys email's subject/body already use, since a
 * push banner needs strictly less content than a full email, never
 * different or additional fields.
 */
final class PushContent
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {}
}
