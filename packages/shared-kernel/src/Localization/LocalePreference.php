<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Localization;

use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Locale;

/**
 * Language, country, currency, and timezone are independent user
 * preferences (ADR-002 / docs/architecture/localization.md) — this type
 * exists specifically so no module is tempted to derive one from another
 * (e.g. assuming USD because the language is English).
 */
final class LocalePreference
{
    public function __construct(
        public readonly Locale $locale,
        public readonly string $countryCode,
        public readonly Currency $currency,
        public readonly string $timezone,
    ) {}

    public function withLocale(Locale $locale): self
    {
        return new self($locale, $this->countryCode, $this->currency, $this->timezone);
    }

    public function withCurrency(Currency $currency): self
    {
        return new self($this->locale, $this->countryCode, $currency, $this->timezone);
    }
}
