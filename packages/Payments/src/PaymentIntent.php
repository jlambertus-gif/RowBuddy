<?php

declare(strict_types=1);

namespace RowBuddy\Payments;

use DateTimeImmutable;
use RowBuddy\Payments\Events\AuthorizationCancelled;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\Payments\Events\PaymentCaptured;
use RowBuddy\Payments\Events\PaymentCaptureFailed;
use RowBuddy\Payments\Events\PaymentRefunded;
use RowBuddy\Payments\Exceptions\IllegalStateTransition;
use RowBuddy\Payments\Exceptions\InvalidRefundAmount;
use RowBuddy\Payments\Exceptions\TransactionValueLimitExceeded;
use RowBuddy\Payments\Exceptions\UnsupportedCurrency;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Aggregate root for the financial lifecycle of a single won auction
 * (Phase 4, extended Phase 5 per ADR-019, extended Phase 6 per ADR-022).
 * Models authorization, capture/cancellation, and dispute-driven refund
 * only — `Held` and `ReleasedToSeller` still do not exist here, in any
 * form: seller payout execution stays out of scope (ADR-019 §5).
 *
 * Tracks its own lifecycle keyed by `auctionId` and `winningBidId` rather
 * than any reference to `Auction`'s own status (ADR-014) — `Auction`
 * gains no payment-related states, and this aggregate never queries or
 * mutates it. `auctionId`, `winningBidId`, `sellerId`, `buyerId`, and
 * `amount` arrive here as already-decided facts (sourced from
 * `AuctionWon` by a future application service), the same way
 * `Auction::selectWinningBid()` takes an already-decided winning bid as a
 * primitive rather than computing it itself.
 *
 * Unlike Phase 4, this aggregate is no longer immutable-after-creation:
 * `capture()`/`failCapture()`/`cancelAuthorization()` are real, in-place
 * transitions on an already-persisted record (ADR-019 §1) — `Auction`'s
 * mutable-aggregate shape, not `Bid`'s append-only one.
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
        public readonly ?string $stripePaymentIntentId,
        private PaymentIntentStatus $status,
        public readonly DateTimeImmutable $decidedAt,
        private ?Money $refundedAmount = null,
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
     * `stripePaymentIntentId` is the real Stripe object this authorization
     * created — required so a later `capture()`/`cancelAuthorization()`
     * knows which Stripe PaymentIntent to act on.
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
        string $stripePaymentIntentId,
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
            stripePaymentIntentId: $stripePaymentIntentId,
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
     * a persisted record at all. No Stripe PaymentIntent id exists for a
     * declined attempt.
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
            stripePaymentIntentId: null,
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
        ?string $stripePaymentIntentId,
        PaymentIntentStatus $status,
        DateTimeImmutable $decidedAt,
        ?Money $refundedAmount = null,
    ): self {
        return new self(
            id: $id,
            auctionId: $auctionId,
            winningBidId: $winningBidId,
            sellerId: $sellerId,
            buyerId: $buyerId,
            amount: $amount,
            feeAmount: $feeAmount,
            stripePaymentIntentId: $stripePaymentIntentId,
            status: $status,
            decidedAt: $decidedAt,
            refundedAmount: $refundedAmount,
        );
    }

    public function status(): PaymentIntentStatus
    {
        return $this->status;
    }

    /**
     * Records a successful capture (ADR-019 §2/§3) — the transfer this
     * payment backs was confirmed, and the real Stripe capture call
     * succeeded.
     *
     * @throws IllegalStateTransition
     */
    public function capture(ClockInterface $clock): void
    {
        $this->guardStatus(PaymentIntentStatus::Authorized, 'capture');

        $this->status = PaymentIntentStatus::Captured;
        $this->recordedEvents[] = new PaymentCaptured($clock, $this->id, $this->auctionId);
    }

    /**
     * The transfer was confirmed, but the real Stripe capture call itself
     * failed (ADR-019 §2) — an expected, anticipatable business outcome,
     * distinct from `cancelAuthorization()` below.
     *
     * @throws IllegalStateTransition
     */
    public function failCapture(string $reason, ClockInterface $clock): void
    {
        $this->guardStatus(PaymentIntentStatus::Authorized, 'fail capture');

        $this->status = PaymentIntentStatus::CaptureFailed;
        $this->recordedEvents[] = new PaymentCaptureFailed($clock, $this->id, $this->auctionId, $reason);
    }

    /**
     * Voids the authorization without ever attempting a capture (ADR-018
     * §2/§4, ADR-019 §2) — the transfer window expired unconfirmed, or a
     * buyer/seller default was recorded, or re-authorization failed
     * before any capture was attempted.
     *
     * @throws IllegalStateTransition
     */
    public function cancelAuthorization(string $reason, ClockInterface $clock): void
    {
        $this->guardStatus(PaymentIntentStatus::Authorized, 'cancel the authorization');

        $this->status = PaymentIntentStatus::Cancelled;
        $this->recordedEvents[] = new AuthorizationCancelled($clock, $this->id, $this->auctionId, $reason);
    }

    /**
     * A dispute resolved in the buyer's favor, fully or partially
     * (ADR-022 §1) — `amount` must be positive, denominated in the same
     * currency as the captured total, and must never exceed it; this is
     * the only ceiling this aggregate enforces, since it never
     * distinguishes the bid amount from the platform fee within `amount`
     * (ADR-022 §5). Whether this call represents a "full refund" or a
     * "split" is not this aggregate's concern — `packages/Disputes`
     * records that distinction itself.
     *
     * The refunded amount is retained (`refundedAmount()`) so the
     * distinction between what was refunded and what remains of the
     * captured total is never lost, even though `Refunded` remains the
     * single lifecycle status regardless of whether the refund was full
     * or partial (ADR-022 §3).
     *
     * Callers must call this only after the real Stripe refund has
     * already succeeded (see `PaymentCaptureService::refund()`'s own
     * docblock for the exact ordering this aggregate depends on) — this
     * method itself has no way to enforce that externally, but it
     * re-validates the same invariants `assertRefundable()` checks, so a
     * caller that skips the pre-check is still protected against an
     * inconsistent transition.
     *
     * @throws IllegalStateTransition
     * @throws InvalidRefundAmount
     */
    public function refund(Money $amount, string $reason, ClockInterface $clock): void
    {
        $this->assertRefundable($amount);

        $this->status = PaymentIntentStatus::Refunded;
        $this->refundedAmount = $amount;
        $this->recordedEvents[] = new PaymentRefunded($clock, $this->id, $this->auctionId, $amount, $reason);
    }

    /**
     * The read-only half of `refund()`'s invariants (status must be
     * `Captured`; amount positive, same currency, never exceeding the
     * captured total) — deliberately callable without mutating anything,
     * so `PaymentCaptureService::refund()` can validate a request
     * *before* ever calling Stripe. An invalid request must never reach
     * the external API.
     *
     * @throws IllegalStateTransition
     * @throws InvalidRefundAmount
     */
    public function assertRefundable(Money $amount): void
    {
        $this->guardStatus(PaymentIntentStatus::Captured, 'refund');
        $this->guardRefundAmount($amount);
    }

    public function refundedAmount(): ?Money
    {
        return $this->refundedAmount;
    }

    /**
     * How much of the captured total has not been refunded — the full
     * captured amount if nothing has been refunded yet, `amount` minus
     * `refundedAmount` otherwise. This is how the remaining captured
     * balance is represented after a partial refund: as a computed
     * value, not a separately persisted field, since it is always
     * exactly derivable from `amount` and `refundedAmount`.
     */
    public function remainingCapturedAmount(): Money
    {
        return $this->refundedAmount === null
            ? $this->amount
            : $this->amount->subtract($this->refundedAmount);
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

    /**
     * @throws IllegalStateTransition
     */
    private function guardStatus(PaymentIntentStatus $expected, string $attemptedTransition): void
    {
        if ($this->status !== $expected) {
            throw IllegalStateTransition::forPaymentIntent($this->id, $attemptedTransition, $this->status);
        }
    }

    /**
     * @throws InvalidRefundAmount
     */
    private function guardRefundAmount(Money $amount): void
    {
        if ($amount->isZero()) {
            throw InvalidRefundAmount::mustBePositive($this->id);
        }

        if (! $amount->currency->equals($this->amount->currency)) {
            throw InvalidRefundAmount::currencyMismatch($this->id, $amount->currency, $this->amount->currency);
        }

        if ($amount->isGreaterThan($this->amount)) {
            throw InvalidRefundAmount::exceedsCapturedTotal($this->id, $amount, $this->amount);
        }
    }
}
