<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\WebhookEventRepository;
use RowBuddy\Payments\Exceptions\WebhookEventAlreadyProcessed;
use RowBuddy\Payments\ProcessedWebhookEvent;

final class InMemoryWebhookEventRepository implements WebhookEventRepository
{
    /** @var array<string, ProcessedWebhookEvent> */
    public array $recorded = [];

    public function record(ProcessedWebhookEvent $event): void
    {
        if (isset($this->recorded[$event->stripeEventId])) {
            throw WebhookEventAlreadyProcessed::forStripeEventId($event->stripeEventId);
        }

        $this->recorded[$event->stripeEventId] = $event;
    }
}
