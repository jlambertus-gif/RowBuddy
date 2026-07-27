<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence;

use DateTimeImmutable;
use RowBuddy\QueuePresence\Events\EvidencePhotoRecorded;
use RowBuddy\QueuePresence\Events\GpsPingRecorded;
use RowBuddy\QueuePresence\Events\PresenceSessionEnded;
use RowBuddy\QueuePresence\Events\PresenceSessionStarted;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Aggregate root for a seller's physical-presence claim at a queue (Phase 2,
 * docs/roadmap.md). Owns only the claim's own Active/Ended lifecycle and
 * the signals recorded against it (GPS pings, evidence photo) while Active
 * — the v1 confidence-scoring engine that turns those signals into a
 * score/tier, the "one active session per seller per queue" invariant, and
 * persistence are all deliberately out of scope here (later sprints).
 */
final class PresenceSession
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly string $queueId,
        public readonly string $sellerId,
        public readonly DateTimeImmutable $startedAt,
        private PresenceSessionStatus $status,
    ) {}

    public static function start(
        string $id,
        string $queueId,
        string $sellerId,
        ClockInterface $clock,
    ): self {
        $session = new self(
            id: $id,
            queueId: $queueId,
            sellerId: $sellerId,
            startedAt: $clock->now(),
            status: PresenceSessionStatus::Active,
        );

        $session->recordedEvents[] = new PresenceSessionStarted($clock, $id, $queueId, $sellerId);

        return $session;
    }

    public function status(): PresenceSessionStatus
    {
        return $this->status;
    }

    /**
     * @throws PresenceSessionNotActive
     */
    public function recordGpsPing(float $latitude, float $longitude, float $accuracyInMeters, ClockInterface $clock): void
    {
        $this->guardActive();

        $this->recordedEvents[] = new GpsPingRecorded($clock, $this->id, $latitude, $longitude, $accuracyInMeters);
    }

    /**
     * @throws PresenceSessionNotActive
     */
    public function recordEvidencePhoto(string $evidenceReference, ClockInterface $clock): void
    {
        $this->guardActive();

        $this->recordedEvents[] = new EvidencePhotoRecorded($clock, $this->id, $evidenceReference);
    }

    /**
     * @throws PresenceSessionNotActive
     */
    public function end(ClockInterface $clock): void
    {
        $this->guardActive();

        $this->status = PresenceSessionStatus::Ended;
        $this->recordedEvents[] = new PresenceSessionEnded($clock, $this->id);
    }

    /**
     * @throws PresenceSessionNotActive
     */
    private function guardActive(): void
    {
        if ($this->status !== PresenceSessionStatus::Active) {
            throw PresenceSessionNotActive::forSessionId($this->id);
        }
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
