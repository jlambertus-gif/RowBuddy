<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * The authorization was voided without ever attempting a capture
 * (ADR-019 §2) — a transfer window expired unconfirmed, or a
 * buyer/seller default was recorded before the window itself elapsed.
 * Distinct from `PaymentCaptureFailed`, which represents a real, failed
 * attempt to take the money.
 */
final class AuthorizationCancelled extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $paymentIntentId,
        private readonly string $auctionId,
        private readonly string $reason,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'payments.authorization_cancelled';
    }

    public function payload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId,
            'auction_id' => $this->auctionId,
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
