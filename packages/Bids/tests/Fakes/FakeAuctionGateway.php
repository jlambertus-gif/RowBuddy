<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use DateTimeImmutable;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;

final class FakeAuctionGateway implements AuctionGateway
{
    /** @var array<string, AuctionLockResult> */
    private array $lockResults = [];

    /** @var array<string, AuctionLockResult> */
    private array $acceptedBidEffectsResults = [];

    /** @var list<array{0: string, 1: DateTimeImmutable}> */
    public array $acceptedBidEffectsCalls = [];

    public function stub(string $auctionId, AuctionLockResult $result): void
    {
        $this->lockResults[$auctionId] = $result;
    }

    public function stubAcceptedBidEffects(string $auctionId, AuctionLockResult $result): void
    {
        $this->acceptedBidEffectsResults[$auctionId] = $result;
    }

    public function lockAndCheckForBidding(string $auctionId): AuctionLockResult
    {
        return $this->lockResults[$auctionId] ?? new AuctionLockResult(null, []);
    }

    public function applyAcceptedBidEffects(string $auctionId, DateTimeImmutable $acceptedAt): AuctionLockResult
    {
        $this->acceptedBidEffectsCalls[] = [$auctionId, $acceptedAt];

        return $this->acceptedBidEffectsResults[$auctionId] ?? new AuctionLockResult(null, []);
    }
}
