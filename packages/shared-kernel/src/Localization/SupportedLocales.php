<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Localization;

use RowBuddy\SharedKernel\ValueObjects\Locale;

/**
 * The platform's supported-locale policy (ADR-002): English and Spanish at
 * launch, English as fallback. New locales are added here as the platform
 * expands — the rest of the system should never hardcode a locale list.
 */
final class SupportedLocales
{
    public const DEFAULT = 'en';

    /**
     * @var list<string>
     */
    public const ALL = ['en', 'es'];

    public static function isSupported(Locale $locale): bool
    {
        return in_array($locale->language(), self::ALL, true);
    }

    public static function default(): Locale
    {
        return new Locale(self::DEFAULT);
    }

    public static function resolve(Locale $locale): Locale
    {
        return self::isSupported($locale) ? $locale : self::default();
    }
}
