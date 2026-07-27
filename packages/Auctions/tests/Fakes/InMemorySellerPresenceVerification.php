<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Tests\Fakes;

use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\ValueObjects\PresenceVerificationSnapshot;

final class InMemorySellerPresenceVerification implements SellerPresenceVerification
{
    /** @var array<string, PresenceVerificationSnapshot> */
    private array $snapshots = [];

    public function stub(string $sellerId, string $queueId, PresenceVerificationSnapshot $snapshot): void
    {
        $this->snapshots[$sellerId.'|'.$queueId] = $snapshot;
    }

    public function verificationFor(string $sellerId, string $queueId): ?PresenceVerificationSnapshot
    {
        return $this->snapshots[$sellerId.'|'.$queueId] ?? null;
    }
}
