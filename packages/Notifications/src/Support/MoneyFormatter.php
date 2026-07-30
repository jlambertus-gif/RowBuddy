<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Support;

use NumberFormatter;
use RowBuddy\SharedKernel\ValueObjects\Money;
use RuntimeException;

/**
 * Locale-aware monetary formatting for notification templates (ADR-025
 * §9/§10) — the amount's own {@see Money::$currency} decides the symbol/
 * code; the recipient's resolved language decides grouping/decimal
 * conventions (e.g. "$1,234.56" for `en` vs. "1234,56 $" for `es`).
 * Never converts between currencies — that would be display-currency
 * conversion, a different concern this project has not approved
 * anywhere.
 */
final class MoneyFormatter
{
    public static function format(Money $amount, string $language): string
    {
        $formatter = new NumberFormatter($language, NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount->minorUnits / 100, (string) $amount->currency);

        if ($formatted === false) {
            throw new RuntimeException(
                "Failed to format money amount for language [{$language}]: {$formatter->getErrorMessage()}"
            );
        }

        return $formatted;
    }
}
