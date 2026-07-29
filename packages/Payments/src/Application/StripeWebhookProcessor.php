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
 * received (ADR-015 §1). Beyond recording, reacts to exactly one event
 * type — `payment_intent.canceled` — reconciled via
 * {@see StripeCancellationReconciliationService} (ADR-019 §7), a
 * secondary, defensive role: ADR-018's scheduled sweep remains the
 * primary mechanism for handling approaching expiry. Every other event
 * type is still recorded but not otherwise reacted to, left for whenever
 * a concrete need for it exists, the same posture this project already
 * takes toward other explicitly-deferred mechanisms.
 */
final class StripeWebhookProcessor
{
    public function __construct(
        private readonly WebhookEventRepository $webhookEvents,
        private readonly StripeCancellationReconciliationService $cancellationReconciler,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return bool true if this call actually processed the event, false
     *              if it was already processed (an idempotent no-op —
     *              e.g. Stripe redelivering after a slow or missed
     *              response)
     */
    public function process(string $stripeEventId, string $eventType, string $objectId): bool
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

        if ($eventType === 'payment_intent.canceled') {
            $this->cancellationReconciler->reconcileCancellation($objectId);
        }

        return true;
    }
}
