<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Tests\Fakes;

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Exceptions\PresenceSessionAlreadyConsumed;

/**
 * Also simulates the presence_session_id uniqueness constraint from the
 * real Eloquent adapter (Sprint 2), so ADR-009 §4's MVP restriction is
 * exercised by fast, database-free application-service tests too.
 */
final class InMemoryAuctionRepository implements AuctionRepository
{
    /** @var array<string, Auction> */
    public array $saved = [];

    public function save(Auction $auction): void
    {
        if ($this->consumedByAnotherAuction($auction)) {
            throw PresenceSessionAlreadyConsumed::forPresenceSessionId($auction->presenceSessionId);
        }

        $this->saved[$auction->id] = $auction;
    }

    public function findById(string $id): ?Auction
    {
        return $this->saved[$id] ?? null;
    }

    public function findByIdForUpdate(string $id): ?Auction
    {
        return $this->saved[$id] ?? null;
    }

    private function consumedByAnotherAuction(Auction $auction): bool
    {
        foreach ($this->saved as $existing) {
            if ($existing->id !== $auction->id && $existing->presenceSessionId === $auction->presenceSessionId) {
                return true;
            }
        }

        return false;
    }
}
