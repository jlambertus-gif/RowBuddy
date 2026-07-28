<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Events;

use DateTimeImmutable;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class AuctionClosingDeadlineExtended extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $auctionId,
        private readonly DateTimeImmutable $newClosesAt,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'auctions.auction_closing_deadline_extended';
    }

    public function payload(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'new_closes_at' => $this->newClosesAt->format(DATE_ATOM),
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
