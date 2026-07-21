<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Localization\SupportedLocales;
use RowBuddy\SharedKernel\ValueObjects\Locale;

it('supports English and Spanish at launch', function () {
    expect(SupportedLocales::isSupported(new Locale('en')))->toBeTrue()
        ->and(SupportedLocales::isSupported(new Locale('es')))->toBeTrue();
});

it('does not support an unlisted locale', function () {
    expect(SupportedLocales::isSupported(new Locale('fr')))->toBeFalse();
});

it('falls back to English for an unsupported locale', function () {
    $resolved = SupportedLocales::resolve(new Locale('fr'));

    expect($resolved->tag)->toBe('en');
});

it('keeps a supported locale as-is when resolving', function () {
    $resolved = SupportedLocales::resolve(new Locale('es'));

    expect($resolved->tag)->toBe('es');
});
