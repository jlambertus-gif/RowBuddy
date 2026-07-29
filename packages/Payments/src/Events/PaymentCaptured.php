<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class PaymentCaptured extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $paymentIntentId,
        private readonly string $auctionId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.payment_captured';
    }

    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'auction_id' => $this->auctionId,
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
