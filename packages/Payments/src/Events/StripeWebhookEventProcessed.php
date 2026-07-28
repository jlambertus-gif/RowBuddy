<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class StripeWebhookEventProcessed extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $stripeEventId,
        private readonly string $eventType,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.stripe_webhook_event_processed';
    }

    public function payload(): array
    {
        return [
            'stripe_event_id' => $this->stripeEventId,
            'event_type' => $this->eventType,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'stripe_webhook_event';
    }

    public function auditSubjectId(): string|int
    {
        return $this->stripeEventId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
