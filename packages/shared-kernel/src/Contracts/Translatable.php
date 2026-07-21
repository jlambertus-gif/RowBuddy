<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Contracts;

use RowBuddy\SharedKernel\ValueObjects\Locale;

/**
 * Marks user-generated content that must record the locale it was
 * originally authored in (per ADR-002 / docs/architecture/localization.md)
 * rather than assuming the viewer's current locale.
 */
interface Translatable
{
    public function originalLocale(): Locale;
}
