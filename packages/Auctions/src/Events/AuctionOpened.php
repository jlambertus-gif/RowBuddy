<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class AuctionOpened extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $auctionId,
        private readonly string $queueId,
        private readonly string $sellerId,
        private readonly string $presenceSessionId,
        private readonly Money $startingPrice,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'auctions.auction_opened';
    }

    public function payload(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'queue_id' => $this->queueId,
            'seller_id' => $this->sellerId,
            'presence_session_id' => $this->presenceSessionId,
            'starting_price_minor_units' => $this->startingPrice->minorUnits,
            'starting_price_currency' => (string) $this->startingPrice->currency,
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
