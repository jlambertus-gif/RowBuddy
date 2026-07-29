<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * The buyer's filing (ADR-021 §1/§2) — always against a `Transfer`
 * already in `Confirmed` status by the time this fires; the eligibility
 * check itself is an application-layer concern (a later sprint), not
 * this event's or this aggregate's job.
 */
final class DisputeOpened extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $disputeId,
        private readonly string $transferId,
        private readonly string $auctionId,
        private readonly string $buyerId,
        private readonly string $sellerId,
        private readonly string $reason,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'disputes.dispute_opened';
    }

    public function payload(): array
    {
        return [
            'dispute_id' => $this->disputeId,
            'transfer_id' => $this->transferId,
            'auction_id' => $this->auctionId,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'reason' => $this->reason,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'dispute';
    }

    public function auditSubjectId(): string|int
    {
        return $this->disputeId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
