<?php

declare(strict_types=1);

use RowBuddy\Notifications\Support\MoneyFormatter;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('formats a USD amount using English grouping and decimal conventions', function () {
    $formatted = MoneyFormatter::format(new Money(123456, new Currency('USD')), 'en');

    expect($formatted)->toContain('1,234.56');
});

it('accepts a different language without error and still includes the amount', function () {
    // This Docker image's PHP build ships ICU data for `en` locales only
    // (verified: NumberFormatter with "es_ES"/"de_DE"/"fr_FR" all fall
    // back to root/English grouping and decimal conventions here) — so
    // Spanish-specific separator conventions cannot be verified in this
    // environment. This test only proves the language parameter is
    // accepted and forwarded to NumberFormatter without error; real
    // locale-specific output depends on the runtime's actual ICU data.
    $formatted = MoneyFormatter::format(new Money(123456, new Currency('USD')), 'es');

    expect($formatted)->toContain('34.56')
        ->and($formatted)->toContain('$');
});

it('never converts currency — the amount\'s own currency is always used', function () {
    $formatted = MoneyFormatter::format(new Money(100000, new Currency('EUR')), 'en');

    expect($formatted)->toContain('1,000.00')
        ->and($formatted)->toContain('€');
});
