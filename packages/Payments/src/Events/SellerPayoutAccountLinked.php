<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class SellerPayoutAccountLinked extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $sellerId,
        private readonly string $stripeAccountId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.seller_payout_account_linked';
    }

    public function payload(): array
    {
        return [
            'seller_id' => $this->sellerId,
            'stripe_account_id' => $this->stripeAccountId,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'seller_payout_account';
    }

    public function auditSubjectId(): string|int
    {
        return $this->sellerId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
