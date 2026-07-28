<?php

declare(strict_types=1);

namespace RowBuddy\Payments;

use DateTimeImmutable;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\Payments\Exceptions\TransactionValueLimitExceeded;
use RowBuddy\Payments\Exceptions\UnsupportedCurrency;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Aggregate root for the financial lifecycle of a single won auction
 * (Phase 4). Deliberately models authorization only — `Captured`, `Held`,
 * `ReleasedToSeller`, and `RefundedToBuyer` do not exist here, in any
 * form, per ADR-015: capture depends on a bounded context (Transfers)
 * and a contract that don't exist yet, unlike a same-phase caller
 * arriving in a later sprint.
 *
 * Tracks its own lifecycle keyed by `auctionId` and `winningBidId` rather
 * than any reference to `Auction`'s own status (ADR-014) — `Auction`
 * gains no payment-related states, and this aggregate never queries or
 * mutates it. `auctionId`, `winningBidId`, `sellerId`, `buyerId`, and
 * `amount` arrive here as already-decided facts (sourced from
 * `AuctionWon` by a future application service), the same way
 * `Auction::selectWinningBid()` takes an already-decided winning bid as a
 * primitive rather than computing it itself.
 */
final class PaymentIntent
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly string $auctionId,
        public readonly string $winningBidId,
        public readonly string $sellerId,
        public readonly string $buyerId,
        public readonly Money $amount,
        public readonly Money $feeAmount,
        private readonly PaymentIntentStatus $status,
        public readonly DateTimeImmutable $decidedAt,
    ) {}

    /**
     * Records a successful authorization. `feeAmount` (FeeCalculator) and
     * `transactionValueLimit` (TransactionValueLimitPolicy) arrive
     * already resolved externally — this aggregate never knows or
     * derives either value, the same way `Auction::open()` never knows or
     * derives its own `closesAt`. It only enforces the two invariants it
     * actually owns: `amount` must be denominated in the same currency
     * the limit is expressed in, and must not exceed it.
     *
     * @throws UnsupportedCurrency
     * @throws TransactionValueLimitExceeded
     */
    public static function authorize(
        string $id,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
        Money $amount,
        Money $feeAmount,
        Money $transactionValueLimit,
        ClockInterface $clock,
    ): self {
        self::guardAmountAgainstLimit($id, $amount, $transactionValueLimit);

        $paymentIntent = new self(
            id: $id,
            auctionId: $auctionId,
            winningBidId: $winningBidId,
            sellerId: $sellerId,
            buyerId: $buyerId,
            amount: $amount,
            feeAmount: $feeAmount,
            status: PaymentIntentStatus::Authorized,
            decidedAt: $clock->now(),
        );

        $paymentIntent->recordedEvents[] = new PaymentAuthorized(
            $clock,
            $id,
            $auctionId,
            $winningBidId,
            $amount,
            $feeAmount,
        );

        return $paymentIntent;
    }

    /**
     * Records a declined/failed authorization attempt as its own,
     * auditable outcome — a failed financial action is never silently
     * discarded (CLAUDE.md), unlike a rejected `Bid`, which never becomes
     * a persisted record at all.
     *
     * @throws UnsupportedCurrency
     * @throws TransactionValueLimitExceeded
     */
    public static function declineAuthorization(
        string $id,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
        Money $amount,
        Money $feeAmount,
        Money $transactionValueLimit,
        string $reason,
        ClockInterface $clock,
    ): self {
        self::guardAmountAgainstLimit($id, $amount, $transactionValueLimit);

        $paymentIntent = new self(
            id: $id,
            auctionId: $auctionId,
            winningBidId: $winningBidId,
            sellerId: $sellerId,
            buyerId: $buyerId,
            amount: $amount,
            feeAmount: $feeAmount,
            status: PaymentIntentStatus::Failed,
            decidedAt: $clock->now(),
        );

        $paymentIntent->recordedEvents[] = new PaymentAuthorizationFailed(
            $clock,
            $id,
            $auctionId,
            $winningBidId,
            $amount,
            $reason,
        );

        return $paymentIntent;
    }

    /**
     * Reconstitutes a PaymentIntent from previously persisted state.
     * Unlike the factories above, this never raises domain events —
     * loading a PaymentIntent back out of storage is not a business event
     * in itself.
     */
    public static function fromPersistence(
        string $id,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
        Money $amount,
        Money $feeAmount,
        PaymentIntentStatus $status,
        DateTimeImmutable $decidedAt,
    ): self {
        return new self(
            id: $id,
            auctionId: $auctionId,
            winningBidId: $winningBidId,
            sellerId: $sellerId,
            buyerId: $buyerId,
            amount: $amount,
            feeAmount: $feeAmount,
            status: $status,
            decidedAt: $decidedAt,
        );
    }

    public function status(): PaymentIntentStatus
    {
        return $this->status;
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

    /**
     * @throws UnsupportedCurrency
     * @throws TransactionValueLimitExceeded
     */
    private static function guardAmountAgainstLimit(string $id, Money $amount, Money $limit): void
    {
        if (! $amount->currency->equals($limit->currency)) {
            throw UnsupportedCurrency::forPaymentIntent($id, $amount->currency, $limit->currency);
        }

        if ($amount->isGreaterThan($limit)) {
            throw TransactionValueLimitExceeded::forPaymentIntent($id, $amount, $limit);
        }
    }
}
