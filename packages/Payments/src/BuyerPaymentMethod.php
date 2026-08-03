<?php

declare(strict_types=1);

namespace RowBuddy\Payments;

use DateTimeImmutable;
use RowBuddy\Payments\Events\BuyerPaymentMethodSaved;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Records the Stripe references needed to reuse a buyer's saved payment
 * method across auctions (Phase 9, ADR-027 Architecture Refinements §4).
 * Deliberately carries no card data of any kind — only the Stripe Customer
 * and PaymentMethod references `AuctionWinAuthorizationService` needs to
 * later authorize a real charge (`PaymentAuthorizationGateway::authorize()`
 * takes `stripePaymentMethodId` as an already-acquired string; this
 * aggregate is what finally supplies it).
 *
 * Unlike {@see SellerPayoutAccount} (linked at most once, never re-linked),
 * a buyer may replace their saved payment method at any time — `save()` is
 * an upsert at the repository layer, one row per buyer, always overwritten
 * by whichever save happened most recently.
 */
final class BuyerPaymentMethod
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $buyerId,
        public readonly string $stripeCustomerId,
        public readonly string $stripePaymentMethodId,
        public readonly DateTimeImmutable $savedAt,
    ) {}

    public static function save(
        string $buyerId,
        string $stripeCustomerId,
        string $stripePaymentMethodId,
        ClockInterface $clock,
    ): self {
        $method = new self(
            buyerId: $buyerId,
            stripeCustomerId: $stripeCustomerId,
            stripePaymentMethodId: $stripePaymentMethodId,
            savedAt: $clock->now(),
        );

        $method->recordedEvents[] = new BuyerPaymentMethodSaved($clock, $buyerId, $stripeCustomerId, $stripePaymentMethodId);

        return $method;
    }

    /**
     * Reconstitutes a BuyerPaymentMethod from previously persisted state.
     * Unlike save() above, this never raises domain events — loading a
     * record back out of storage is not a business event in itself.
     */
    public static function fromPersistence(
        string $buyerId,
        string $stripeCustomerId,
        string $stripePaymentMethodId,
        DateTimeImmutable $savedAt,
    ): self {
        return new self($buyerId, $stripeCustomerId, $stripePaymentMethodId, $savedAt);
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
