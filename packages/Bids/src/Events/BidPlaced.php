<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class BidPlaced extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $bidId,
        private readonly string $auctionId,
        private readonly string $bidderId,
        private readonly Money $amount,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'bids.bid_placed';
    }

    public function payload(): array
    {
        return [
            'bid_id' => $this->bidId,
            'auction_id' => $this->auctionId,
            'bidder_id' => $this->bidderId,
            'amount_minor_units' => $this->amount->minorUnits,
            'amount_currency' => (string) $this->amount->currency,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'bid';
    }

    public function auditSubjectId(): string|int
    {
        return $this->bidId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
