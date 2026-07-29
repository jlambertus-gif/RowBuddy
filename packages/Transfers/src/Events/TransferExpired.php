<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * The transfer window closed with no confirmation from both parties
 * (ADR-018 §3) — distinct from `TransferCancelled`, which represents an
 * explicit default or failure rather than simply running out the clock.
 */
final class TransferExpired extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $transferId,
        private readonly string $auctionId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'transfers.transfer_expired';
    }

    public function payload(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'auction_id' => $this->auctionId,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'transfer';
    }

    public function auditSubjectId(): string|int
    {
        return $this->transferId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
