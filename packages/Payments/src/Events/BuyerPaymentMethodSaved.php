<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class BuyerPaymentMethodSaved extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $buyerId,
        private readonly string $stripeCustomerId,
        private readonly string $stripePaymentMethodId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.buyer_payment_method_saved';
    }

    public function payload(): array
    {
        return [
            'buyer_id' => $this->buyerId,
            'stripe_customer_id' => $this->stripeCustomerId,
            'stripe_payment_method_id' => $this->stripePaymentMethodId,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'buyer_payment_method';
    }

    public function auditSubjectId(): string|int
    {
        return $this->buyerId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
