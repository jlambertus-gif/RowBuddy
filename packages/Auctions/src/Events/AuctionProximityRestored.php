<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class AuctionProximityRestored extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $auctionId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'auctions.auction_proximity_restored';
    }

    public function payload(): array
    {
        return [
            'auction_id' => $this->auctionId,
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
