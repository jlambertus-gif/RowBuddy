<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * An explicit default or failure short-circuits the transfer window
 * (ADR-018 §2/§4) — e.g. re-authorization failed, or a buyer/seller
 * default was recorded before the window itself elapsed.
 */
final class TransferCancelled extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $transferId,
        private readonly string $auctionId,
        private readonly string $reason,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'transfers.transfer_cancelled';
    }

    public function payload(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'auction_id' => $this->auctionId,
            'reason' => $this->reason,
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
