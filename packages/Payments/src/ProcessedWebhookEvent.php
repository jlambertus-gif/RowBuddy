<?php

declare(strict_types=1);

namespace RowBuddy\Payments;

use DateTimeImmutable;
use RowBuddy\Payments\Events\StripeWebhookEventProcessed;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * A single durable record that a given Stripe event id has been received
 * and processed — the idempotency ledger (ADR-015 §1's "webhook
 * handling"). Deliberately carries no reaction to what the event
 * actually was: reacting to specific Stripe event types with business
 * logic (e.g. reconciling a PaymentIntent) is out of scope for this
 * sprint, left for whenever a concrete need for it exists — this class
 * only proves "we have already seen this event," which is what makes
 * replayed/duplicate Stripe deliveries safe to no-op.
 */
final class ProcessedWebhookEvent
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $stripeEventId,
        public readonly string $eventType,
        public readonly DateTimeImmutable $processedAt,
    ) {}

    public static function record(string $stripeEventId, string $eventType, ClockInterface $clock): self
    {
        $event = new self(
            stripeEventId: $stripeEventId,
            eventType: $eventType,
            processedAt: $clock->now(),
        );

        $event->recordedEvents[] = new StripeWebhookEventProcessed($clock, $stripeEventId, $eventType);

        return $event;
    }

    /**
     * Reconstitutes a ProcessedWebhookEvent from previously persisted
     * state. Unlike record() above, this never raises domain events —
     * loading a record back out of storage is not a business event in
     * itself.
     */
    public static function fromPersistence(string $stripeEventId, string $eventType, DateTimeImmutable $processedAt): self
    {
        return new self($stripeEventId, $eventType, $processedAt);
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
