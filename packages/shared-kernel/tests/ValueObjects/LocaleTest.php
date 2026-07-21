<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\Locale;

it('accepts a bare language tag', function () {
    expect((new Locale('en'))->tag)->toBe('en');
});

it('accepts a language-region tag', function () {
    expect((new Locale('en-US'))->tag)->toBe('en-US');
});

it('extracts the language from a language-region tag', function () {
    expect((new Locale('en-US'))->language())->toBe('en');
});

it('rejects an invalid tag shape', function () {
    new Locale('english');
})->throws(ValidationException::class);

it('treats identical tags as equal', function () {
    expect((new Locale('es'))->equals(new Locale('es')))->toBeTrue();
});
