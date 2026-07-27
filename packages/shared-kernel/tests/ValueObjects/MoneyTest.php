<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

function usd(int $minorUnits): Money
{
    return new Money($minorUnits, new Currency('USD'));
}

it('adds two amounts in the same currency', function () {
    expect(usd(1_000)->add(usd(250))->minorUnits)->toBe(1_250);
});

it('rejects construction with a negative amount', function () {
    usd(-1);
})->throws(ValidationException::class);

it('rejects adding different currencies', function () {
    $usd = usd(100);
    $mxn = new Money(100, new Currency('MXN'));

    $usd->add($mxn);
})->throws(ValidationException::class);

it('rejects subtracting a larger amount', function () {
    usd(100)->subtract(usd(200));
})->throws(ValidationException::class);

it('computes a whole-number percentage', function () {
    expect(usd(1_000)->percentage(10)->minorUnits)->toBe(100);
});

it('reports zero correctly', function () {
    expect(usd(0)->isZero())->toBeTrue();
    expect(usd(1)->isZero())->toBeFalse();
});

it('reports greater-than correctly', function () {
    expect(usd(200)->isGreaterThan(usd(100)))->toBeTrue()
        ->and(usd(100)->isGreaterThan(usd(200)))->toBeFalse()
        ->and(usd(100)->isGreaterThan(usd(100)))->toBeFalse();
});

it('rejects comparing different currencies', function () {
    $usd = usd(100);
    $mxn = new Money(100, new Currency('MXN'));

    $usd->isGreaterThan($mxn);
})->throws(ValidationException::class);
