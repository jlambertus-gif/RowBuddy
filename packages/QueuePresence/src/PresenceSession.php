<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence;

use DateTimeImmutable;
use RowBuddy\QueuePresence\Application\ConfidenceRecomputer;
use RowBuddy\QueuePresence\Events\EvidencePhotoRecorded;
use RowBuddy\QueuePresence\Events\GpsPingRecorded;
use RowBuddy\QueuePresence\Events\PresenceSessionEnded;
use RowBuddy\QueuePresence\Events\PresenceSessionStarted;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\QueuePresence\Scoring\ConfidenceScorer;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Aggregate root for a seller's physical-presence claim at a queue (Phase 2,
 * docs/roadmap.md). Owns only the claim's own Active/Ended lifecycle, the
 * signals recorded against it (GPS pings, evidence photo) while Active, and
 * its own elapsed-duration calculation — turning those facts into a
 * confidence score/tier is {@see ConfidenceScorer}
 * and {@see ConfidenceRecomputer}'s job,
 * not this aggregate's.
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
        private ?DateTimeImmutable $endedAt = null,
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

    /**
     * Reconstitutes a PresenceSession from previously persisted state.
     * Unlike start() above, this never raises domain events — loading a
     * session back out of storage is not a business event in itself.
     */
    public static function fromPersistence(
        string $id,
        string $queueId,
        string $sellerId,
        DateTimeImmutable $startedAt,
        PresenceSessionStatus $status,
        ?DateTimeImmutable $endedAt,
    ): self {
        return new self(
            id: $id,
            queueId: $queueId,
            sellerId: $sellerId,
            startedAt: $startedAt,
            status: $status,
            endedAt: $endedAt,
        );
    }

    public function status(): PresenceSessionStatus
    {
        return $this->status;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Elapsed presence time in seconds: from start to now while Active, or
     * from start to the moment it ended once Ended — frozen at that point,
     * not still growing after the session is over.
     */
    public function presenceDurationInSeconds(ClockInterface $clock): int
    {
        $end = $this->endedAt ?? $clock->now();

        return max(0, $end->getTimestamp() - $this->startedAt->getTimestamp());
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
        $this->endedAt = $clock->now();
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
