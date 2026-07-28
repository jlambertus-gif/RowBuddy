<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Contracts\WebhookEventRepository;
use RowBuddy\Payments\Exceptions\WebhookEventAlreadyProcessed;
use RowBuddy\Payments\ProcessedWebhookEvent;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Idempotently records that a verified Stripe webhook event has been
 * received (ADR-015 §1). Deliberately does not react to specific event
 * types with business logic — e.g. reconciling a PaymentIntent against
 * `payment_intent.payment_failed` — that is out of scope for this sprint
 * and left for whenever a concrete need for it exists, the same posture
 * this project already takes toward other explicitly-deferred mechanisms
 * (no scheduler, no cap on soft-close extensions).
 */
final class StripeWebhookProcessor
{
    public function __construct(
        private readonly WebhookEventRepository $webhookEvents,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return bool true if this call actually processed the event, false
     *              if it was already processed (an idempotent no-op —
     *              e.g. Stripe redelivering after a slow or missed
     *              response)
     */
    public function process(string $stripeEventId, string $eventType): bool
    {
        $event = ProcessedWebhookEvent::record($stripeEventId, $eventType, $this->clock);

        try {
            $this->webhookEvents->record($event);
        } catch (WebhookEventAlreadyProcessed) {
            return false;
        }

        foreach ($event->releaseEvents() as $domainEvent) {
            $this->events->publish($domainEvent);
        }

        return true;
    }
}
