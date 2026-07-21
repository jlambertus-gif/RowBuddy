<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;

/**
 * A BCP-47-lite language tag (e.g. "en", "es", "en-US"). This type only
 * validates *shape*; whether a given locale is currently supported by the
 * platform is a separate policy question (see the Localization namespace's
 * SupportedLocales), kept apart so this value object stays reusable for any
 * locale, not just the ones RowBuddy currently ships.
 */
final class Locale
{
    public readonly string $tag;

    public function __construct(string $tag)
    {
        $normalized = trim($tag);

        if (! preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $normalized)) {
            throw new ValidationException(
                "Invalid locale tag [{$tag}]: expected a format like \"en\" or \"en-US\"."
            );
        }

        $this->tag = $normalized;
    }

    public function language(): string
    {
        return substr($this->tag, 0, 2);
    }

    public function equals(Locale $other): bool
    {
        return $this->tag === $other->tag;
    }

    public function __toString(): string
    {
        return $this->tag;
    }
}
