<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * A failed authorization attempt is itself a financial action that must
 * be audited (CLAUDE.md security rules) — never silently discarded the
 * way a rejected `Bid` never becomes a persisted record.
 */
final class PaymentAuthorizationFailed extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $paymentIntentId,
        private readonly string $auctionId,
        private readonly string $winningBidId,
        private readonly Money $amount,
        private readonly string $reason,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.payment_authorization_failed';
    }

    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'auction_id' => $this->auctionId,
            'winning_bid_id' => $this->winningBidId,
            'amount_minor_units' => $this->amount->minorUnits,
            'amount_currency' => (string) $this->amount->currency,
            'reason' => $this->reason,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'payment_intent';
    }

    public function auditSubjectId(): string|int
    {
        return $this->paymentIntentId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
