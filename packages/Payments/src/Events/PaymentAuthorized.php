<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class PaymentAuthorized extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $paymentIntentId,
        private readonly string $auctionId,
        private readonly string $winningBidId,
        private readonly Money $amount,
        private readonly Money $feeAmount,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.payment_authorized';
    }

    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'auction_id' => $this->auctionId,
            'winning_bid_id' => $this->winningBidId,
            'amount_minor_units' => $this->amount->minorUnits,
            'amount_currency' => (string) $this->amount->currency,
            'fee_amount_minor_units' => $this->feeAmount->minorUnits,
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
