<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * The real payment-capture trigger (ADR-019 §6) — raised only once both
 * seller and buyer have independently confirmed.
 */
final class TransferConfirmed extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $transferId,
        private readonly string $auctionId,
        private readonly string $winningBidId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'transfers.transfer_confirmed';
    }

    public function payload(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'auction_id' => $this->auctionId,
            'winning_bid_id' => $this->winningBidId,
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
