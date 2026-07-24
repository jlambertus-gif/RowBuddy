<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application;

use RowBuddy\Queues\Application\Discovery\CoverageAreaAssigner;
use RowBuddy\Queues\Contracts\DomainEventPublisher;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Exceptions\InvalidQueueStatusTransition;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\Queues\Gating\QueueGateChecker;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

/**
 * Orchestrates the Administration moderation queue from ADR-005: listing
 * pending submissions, and the approve/reject/publish transitions. The
 * transition rules themselves (only pending -> approved/rejected, only
 * approved -> published) live on the Queue aggregate, not here — this
 * class only loads, re-checks the legal gate, transitions, and persists.
 *
 * Re-runs the restricted-category/jurisdiction gate at approval time, per
 * ADR-005 ("must run at approval time, not only at auction-creation
 * time") — a category or jurisdiction that was permitted at submission
 * time may no longer be by the time an admin reviews it, since
 * jurisdiction rules are effective-dated. A blocked approval is refused
 * (QueueSubmissionBlocked) rather than auto-rejecting the queue: an
 * automated block is not the same as a human-reviewed rejection reason,
 * so the admin decides whether to reject it explicitly.
 *
 * Depends only on domain-facing ports — no Eloquent, no Laravel container
 * — so every business decision here is testable with plain in-memory
 * fakes, no database.
 */
final class QueueModerationService
{
    public function __construct(
        private readonly QueueRepository $queues,
        private readonly QueueGateChecker $gateChecker,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
        private readonly CoverageAreaAssigner $coverageAreaAssigner,
    ) {}

    /**
     * @return list<Queue>
     */
    public function listPending(): array
    {
        return $this->queues->findByStatus(QueueStatus::Pending);
    }

    /**
     * @throws NotFoundException
     * @throws QueueSubmissionBlocked
     * @throws InvalidQueueStatusTransition
     */
    public function approve(string $queueId, string $approvedByUserId): Queue
    {
        $queue = $this->findOrFail($queueId);

        $this->gateChecker->assertNotBlocked($queue->category, $queue->jurisdictionCountry, $this->clock->now());

        $queue->approve($approvedByUserId, $this->clock);

        $this->persist($queue);

        return $queue;
    }

    /**
     * @throws NotFoundException
     * @throws InvalidQueueStatusTransition
     */
    public function reject(string $queueId, string $rejectedByUserId, string $reason): Queue
    {
        $queue = $this->findOrFail($queueId);

        $queue->reject($rejectedByUserId, $reason, $this->clock);

        $this->persist($queue);

        return $queue;
    }

    /**
     * @throws NotFoundException
     * @throws InvalidQueueStatusTransition
     */
    public function publish(string $queueId): Queue
    {
        $queue = $this->findOrFail($queueId);

        $queue->publish($this->clock);

        $this->persist($queue);
        $this->coverageAreaAssigner->assignDefaultCoverageArea($queue);

        return $queue;
    }

    /**
     * @throws NotFoundException
     */
    private function findOrFail(string $queueId): Queue
    {
        $queue = $this->queues->findById($queueId);

        if ($queue === null) {
            throw new NotFoundException("Queue [{$queueId}] not found.");
        }

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
