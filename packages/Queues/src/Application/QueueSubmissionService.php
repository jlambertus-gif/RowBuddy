<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application;

use RowBuddy\Queues\Contracts\DomainEventPublisher;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\Queues\Gating\QueueGateChecker;
use RowBuddy\Queues\Queue;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\Geofence;

/**
 * Orchestrates the two queue-creation use cases from ADR-005
 * (user-submitted, pending approval, and admin/partner direct publish).
 * Both are gated by {@see QueueGateChecker} before the aggregate is even
 * constructed — a blocked attempt never reaches persistence, admin-curated
 * included, since the legal gate must hold regardless of authorship.
 *
 * Depends only on domain-facing ports and the pure QueueGateChecker — no
 * Eloquent, no Laravel container, no framework types — so every business
 * decision here is testable with plain in-memory fakes, no database.
 */
final class QueueSubmissionService
{
    public function __construct(
        private readonly QueueRepository $queues,
        private readonly QueueGateChecker $gateChecker,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws QueueSubmissionBlocked
     */
    public function submitForApproval(
        string $id,
        string $category,
        string $jurisdictionCountry,
        Geofence $geofence,
        string $submittedByUserId,
    ): Queue {
        $this->gateChecker->assertNotBlocked($category, $jurisdictionCountry, $this->clock->now());

        $queue = Queue::submitForApproval($id, $category, $jurisdictionCountry, $geofence, $submittedByUserId, $this->clock);

        $this->persist($queue);

        return $queue;
    }

    /**
     * @throws QueueSubmissionBlocked
     */
    public function publishDirectly(
        string $id,
        string $category,
        string $jurisdictionCountry,
        Geofence $geofence,
        string $organizerReference,
    ): Queue {
        $this->gateChecker->assertNotBlocked($category, $jurisdictionCountry, $this->clock->now());

        $queue = Queue::publishDirectly($id, $category, $jurisdictionCountry, $geofence, $organizerReference);

        $this->persist($queue);

        return $queue;
    }

    private function persist(Queue $queue): void
    {
        $this->queues->save($queue);

        foreach ($queue->releaseEvents() as $event) {
            $this->events->publish($event);
        }
    }
}
