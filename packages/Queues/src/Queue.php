<?php

declare(strict_types=1);

namespace RowBuddy\Queues;

use RowBuddy\Queues\Events\QueueApproved;
use RowBuddy\Queues\Events\QueuePublished;
use RowBuddy\Queues\Events\QueueRejected;
use RowBuddy\Queues\Events\QueueSubmittedForApproval;
use RowBuddy\Queues\Exceptions\InvalidQueueStatusTransition;
use RowBuddy\Queues\ValueObjects\QueueAuthorship;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Geofence;

/**
 * Aggregate root for a single queue definition. Owns the pending/approved/
 * published/rejected authorship lifecycle from ADR-005
 * (docs/decisions/005-hybrid-queue-authorship.md) — persistence, jurisdiction
 * gating, and discovery are deliberately out of scope here (later sprints).
 */
final class Queue
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly string $category,
        public readonly string $jurisdictionCountry,
        public readonly Geofence $geofence,
        public readonly QueueAuthorship $authorship,
        public readonly ?string $organizerReference,
        private QueueStatus $status,
    ) {}

    public static function publishDirectly(
        string $id,
        string $category,
        string $jurisdictionCountry,
        Geofence $geofence,
        string $organizerReference,
    ): self {
        return new self(
            id: $id,
            category: $category,
            jurisdictionCountry: $jurisdictionCountry,
            geofence: $geofence,
            authorship: QueueAuthorship::AdminCurated,
            organizerReference: $organizerReference,
            status: QueueStatus::Published,
        );
    }

    /**
     * Reconstitutes a Queue from previously persisted state. Unlike the
     * creation factories above, this never raises domain events — loading
     * a queue back out of storage is not a business event in itself.
     */
    public static function fromPersistence(
        string $id,
        string $category,
        string $jurisdictionCountry,
        Geofence $geofence,
        QueueAuthorship $authorship,
        ?string $organizerReference,
        QueueStatus $status,
    ): self {
        return new self(
            id: $id,
            category: $category,
            jurisdictionCountry: $jurisdictionCountry,
            geofence: $geofence,
            authorship: $authorship,
            organizerReference: $organizerReference,
            status: $status,
        );
    }

    public static function submitForApproval(
        string $id,
        string $category,
        string $jurisdictionCountry,
        Geofence $geofence,
        string $submittedByUserId,
        ClockInterface $clock,
    ): self {
        $queue = new self(
            id: $id,
            category: $category,
            jurisdictionCountry: $jurisdictionCountry,
            geofence: $geofence,
            authorship: QueueAuthorship::UserSubmitted,
            organizerReference: null,
            status: QueueStatus::Pending,
        );

        $queue->recordedEvents[] = new QueueSubmittedForApproval($clock, $id, $submittedByUserId);

        return $queue;
    }

    public function status(): QueueStatus
    {
        return $this->status;
    }

    /**
     * @throws InvalidQueueStatusTransition
     */
    public function approve(string $approvedByUserId, ClockInterface $clock): void
    {
        if ($this->status !== QueueStatus::Pending) {
            throw InvalidQueueStatusTransition::from($this->status, QueueStatus::Approved);
        }

        $this->status = QueueStatus::Approved;
        $this->recordedEvents[] = new QueueApproved($clock, $this->id, $approvedByUserId);
    }

    /**
     * @throws InvalidQueueStatusTransition
     */
    public function publish(ClockInterface $clock): void
    {
        if ($this->status !== QueueStatus::Approved) {
            throw InvalidQueueStatusTransition::from($this->status, QueueStatus::Published);
        }

        $this->status = QueueStatus::Published;
        $this->recordedEvents[] = new QueuePublished($clock, $this->id);
    }

    /**
     * @throws InvalidQueueStatusTransition
     */
    public function reject(string $rejectedByUserId, string $reason, ClockInterface $clock): void
    {
        if ($this->status !== QueueStatus::Pending) {
            throw InvalidQueueStatusTransition::from($this->status, QueueStatus::Rejected);
        }

        $this->status = QueueStatus::Rejected;
        $this->recordedEvents[] = new QueueRejected($clock, $this->id, $rejectedByUserId, $reason);
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
