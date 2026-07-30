<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * A dispute resolution refunded some or all of a captured charge
 * (ADR-022 §1/§3) — `amount` may equal the full captured total or a
 * lesser amount; this event carries no "full vs. split" distinction of
 * its own, since that label belongs to `packages/Disputes`' own
 * resolution record, not to `PaymentIntent`.
 */
final class PaymentRefunded extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $paymentIntentId,
        private readonly string $auctionId,
        private readonly Money $amount,
        private readonly string $reason,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.payment_refunded';
    }

    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'auction_id' => $this->auctionId,
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
