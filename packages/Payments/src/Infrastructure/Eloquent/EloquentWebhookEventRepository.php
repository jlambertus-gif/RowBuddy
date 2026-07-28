<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Payments\Contracts\WebhookEventRepository;
use RowBuddy\Payments\Exceptions\WebhookEventAlreadyProcessed;
use RowBuddy\Payments\ProcessedWebhookEvent;

final class EloquentWebhookEventRepository implements WebhookEventRepository
{
    public function record(ProcessedWebhookEvent $event): void
    {
        try {
            WebhookEventModel::query()->create([
                'stripe_event_id' => $event->stripeEventId,
                'event_type' => $event->eventType,
                'processed_at' => $event->processedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw WebhookEventAlreadyProcessed::forStripeEventId($event->stripeEventId);
        }
    }
}
