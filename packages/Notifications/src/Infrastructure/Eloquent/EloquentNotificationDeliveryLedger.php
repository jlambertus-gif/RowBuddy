<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Translates between the {@see NotificationDeliveryModel} Eloquent
 * record and the {@see NotificationDeliveryLedger} port. `recordDelivered()`
 * silently tolerates a duplicate-key race (two concurrent attempts for
 * the exact same logical delivery) rather than throwing — the caller
 * already checked `alreadyDelivered()` first; a race losing this way
 * means the delivery already happened moments ago, which is exactly the
 * outcome idempotency is meant to guarantee, not an error condition.
 */
final class EloquentNotificationDeliveryLedger implements NotificationDeliveryLedger
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function alreadyDelivered(string $domainEventId, string $recipientId, NotificationType $type): bool
    {
        return NotificationDeliveryModel::query()
            ->where('domain_event_id', $domainEventId)
            ->where('recipient_id', $recipientId)
            ->where('notification_type', $type->value)
            ->exists();
    }

    public function recordDelivered(string $domainEventId, string $recipientId, NotificationType $type): void
    {
        try {
            NotificationDeliveryModel::query()->create([
                'domain_event_id' => $domainEventId,
                'recipient_id' => $recipientId,
                'notification_type' => $type->value,
                'delivered_at' => $this->clock->now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already recorded by a concurrent attempt — the delivery
            // this call is trying to record already happened.
        }
    }
}
