<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

final class TransferSellerConfirmed extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $transferId,
        private readonly GeoPoint $geo,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'transfers.transfer_seller_confirmed';
    }

    public function payload(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'latitude' => $this->geo->latitude,
            'longitude' => $this->geo->longitude,
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
