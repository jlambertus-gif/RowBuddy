<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\ValueObjects;

/**
 * The recipient's raw stored locale preference (ADR-025 §10) — every
 * field is genuinely nullable, mirroring the underlying `users` columns
 * exactly: this snapshot carries only what is actually stored, with no
 * fallback applied. Fallback resolution (English for a missing
 * language, UTC for a missing timezone, per this project's platform
 * fallback rules) is deliberately a rendering-time decision made by the
 * consumer, not baked into this snapshot — there is no established
 * platform-wide default for `countryCode`/`currencyCode` to invent one
 * here (CLAUDE.md: "never assume USD based only on the selected
 * language").
 */
final class RecipientLocalePreferenceSnapshot
{
    public function __construct(
        public readonly ?string $language,
        public readonly ?string $countryCode,
        public readonly ?string $currencyCode,
        public readonly ?string $timezone,
    ) {}
}
