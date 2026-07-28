<?php

declare(strict_types=1);

namespace RowBuddy\Payments;

use DateTimeImmutable;
use RowBuddy\Payments\Contracts\ConnectAccountGateway;
use RowBuddy\Payments\Events\SellerPayoutAccountLinked;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Records that a seller has a Stripe Connect Express account linked —
 * nothing more. Deliberately carries no charges_enabled/payouts_enabled
 * fields of its own: payout eligibility is always read live from Stripe
 * via {@see ConnectAccountGateway}, never cached or derived here, so this
 * aggregate can never go stale relative to Stripe's own account state.
 */
final class SellerPayoutAccount
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $sellerId,
        public readonly string $stripeAccountId,
        public readonly DateTimeImmutable $linkedAt,
    ) {}

    public static function link(string $sellerId, string $stripeAccountId, ClockInterface $clock): self
    {
        $account = new self(
            sellerId: $sellerId,
            stripeAccountId: $stripeAccountId,
            linkedAt: $clock->now(),
        );

        $account->recordedEvents[] = new SellerPayoutAccountLinked($clock, $sellerId, $stripeAccountId);

        return $account;
    }

    /**
     * Reconstitutes a SellerPayoutAccount from previously persisted state.
     * Unlike link() above, this never raises domain events — loading a
     * record back out of storage is not a business event in itself.
     */
    public static function fromPersistence(string $sellerId, string $stripeAccountId, DateTimeImmutable $linkedAt): self
    {
        return new self($sellerId, $stripeAccountId, $linkedAt);
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
