<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use DateTimeImmutable;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * A generic stand-in for "some event a cross-module gateway happened to
 * return" — used only in tests that exercise BidService's handling of
 * AuctionLockResult::proximityEvents. Deliberately not a real Auctions
 * event: packages/Bids must never import packages/Auctions (ADR-012 §5),
 * including from its own tests.
 */
final class FakeDomainEvent implements DomainEvent
{
    public function __construct(private readonly string $name = 'test.fake_event') {}

    public function eventName(): string
    {
        return $this->name;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-23 10:00:00');
    }

    public function payload(): array
    {
        return [];
    }
}
