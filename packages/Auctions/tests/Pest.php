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

/**
 * A closesAt comfortably in the future relative to "now" — for tests
 * that don't care about the exact deadline, only that
 * Auction::open()'s closesAt > openedAt invariant is satisfied.
 */
function aFutureClosesAt(): DateTimeImmutable
{
    return (new DateTimeImmutable)->modify('+30 minutes');
}

function minutesAfter(DateTimeImmutable $from, int $minutes): DateTimeImmutable
{
    return $from->modify("+{$minutes} minutes");
}
