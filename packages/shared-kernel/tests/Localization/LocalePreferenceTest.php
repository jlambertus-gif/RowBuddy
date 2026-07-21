<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Localization\LocalePreference;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Locale;

it('keeps locale, country, currency, and timezone independent', function () {
    $preference = new LocalePreference(
        locale: new Locale('en'),
        countryCode: 'MX',
        currency: new Currency('MXN'),
        timezone: 'America/Mexico_City',
    );

    expect($preference->locale->tag)->toBe('en')
        ->and($preference->countryCode)->toBe('MX')
        ->and((string) $preference->currency)->toBe('MXN');
});

it('changing the locale does not change the currency', function () {
    $preference = new LocalePreference(
        locale: new Locale('en'),
        countryCode: 'MX',
        currency: new Currency('MXN'),
        timezone: 'America/Mexico_City',
    );

    $updated = $preference->withLocale(new Locale('es'));

    expect($updated->locale->tag)->toBe('es')
        ->and((string) $updated->currency)->toBe('MXN');
});
