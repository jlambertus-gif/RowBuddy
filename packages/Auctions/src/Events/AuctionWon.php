<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class AuctionWon extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $auctionId,
        private readonly string $winningBidId,
        private readonly Money $winningAmount,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'auctions.auction_won';
    }

    public function payload(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'winning_bid_id' => $this->winningBidId,
            'winning_amount_minor_units' => $this->winningAmount->minorUnits,
            'winning_amount_currency' => (string) $this->winningAmount->currency,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'auction';
    }

    public function auditSubjectId(): string|int
    {
        return $this->auctionId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
