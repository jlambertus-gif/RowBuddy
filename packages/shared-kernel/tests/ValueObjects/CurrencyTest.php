<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\Currency;

it('normalizes a valid currency code to uppercase', function () {
    expect((new Currency('usd'))->code)->toBe('USD');
});

it('treats two currencies with the same code as equal', function () {
    expect((new Currency('USD'))->equals(new Currency('usd')))->toBeTrue();
});

it('rejects a code that is not a 3-letter ISO 4217 shape', function () {
    new Currency('US');
})->throws(ValidationException::class);

it('rejects a numeric or symbol code', function () {
    new Currency('12$');
})->throws(ValidationException::class);
