<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\NotificationType;

final class InMemoryNotificationDeliveryLedger implements NotificationDeliveryLedger
{
    /** @var list<string> */
    public array $recorded = [];

    public function alreadyDelivered(string $domainEventId, string $recipientId, NotificationType $type, string $channel = 'email'): bool
    {
        return in_array($this->key($domainEventId, $recipientId, $type, $channel), $this->recorded, true);
    }

    public function recordDelivered(string $domainEventId, string $recipientId, NotificationType $type, string $channel = 'email'): void
    {
        $this->recorded[] = $this->key($domainEventId, $recipientId, $type, $channel);
    }

    private function key(string $domainEventId, string $recipientId, NotificationType $type, string $channel): string
    {
        return "{$domainEventId}:{$recipientId}:{$type->value}:{$channel}";
    }
}
