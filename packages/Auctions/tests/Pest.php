<?php

declare(strict_types=1);

use RowBuddy\Auctions\Tests\TestCase;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(TestCase::class)->in(__DIR__);

function usd(int $minorUnits): Money
{
    return new Money($minorUnits, new Currency('USD'));
}
