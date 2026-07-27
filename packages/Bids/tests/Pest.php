<?php

declare(strict_types=1);

use RowBuddy\Bids\Tests\TestCase;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(TestCase::class)->in(__DIR__);

function usd(int $minorUnits): Money
{
    return new Money($minorUnits, new Currency('USD'));
}

function eur(int $minorUnits): Money
{
    return new Money($minorUnits, new Currency('EUR'));
}
