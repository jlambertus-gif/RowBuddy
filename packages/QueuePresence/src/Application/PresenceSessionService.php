<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Application;

use RowBuddy\QueuePresence\Contracts\DomainEventPublisher;
use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Contracts\QueueGeofenceLookup;
use RowBuddy\QueuePresence\Exceptions\PresenceQueueUnavailable;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Orchestrates GPS presence capture: starting a session, recording a GPS
 * ping against it, and ending it. Depends only on domain-facing ports — no
 * Eloquent, no Laravel container — so every business decision here is
 * testable with plain in-memory fakes, no database.
 *
 * Ownership is enforced here, not left to the HTTP layer or a database
 * constraint: a request naming a session that belongs to a different
 * seller is a domain-level authorization failure
 * ({@see PresenceSessionAccessDenied}), the same way the legal gate is
 * enforced in Queues' application services rather than its controllers.
 */
final class PresenceSessionService
{
    public function __construct(
        private readonly PresenceSessionRepository $sessions,
        private readonly GpsPingRepository $gpsPings,
        private readonly QueueGeofenceLookup $queueGeofences,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws PresenceQueueUnavailable
     */
    public function start(string $id, string $queueId, string $sellerId): PresenceSession
    {
        if ($this->queueGeofences->publishedGeofenceFor($queueId) === null) {
            throw PresenceQueueUnavailable::forQueueId($queueId);
        }

        $session = PresenceSession::start($id, $queueId, $sellerId, $this->clock);

        $this->persist($session);

        return $session;
    }

    /**
     * @throws NotFoundException
     * @throws PresenceSessionAccessDenied
     * @throws PresenceQueueUnavailable
     */
    public function recordGpsPing(
        string $pingId,
        string $sessionId,
        string $requestingUserId,
        float $latitude,
        float $longitude,
        float $accuracyInMeters,
    ): PresenceSession {
        $session = $this->findOrFail($sessionId);
        $this->assertOwnedBy($session, $requestingUserId);

        $geofence = $this->queueGeofences->publishedGeofenceFor($session->queueId);

        if ($geofence === null) {
            throw PresenceQueueUnavailable::forQueueId($session->queueId);
        }

        $location = new GeoPoint($latitude, $longitude);
        $withinGeofence = $geofence->contains($location);

        $session->recordGpsPing($latitude, $longitude, $accuracyInMeters, $this->clock);

        $this->persist($session);

        $this->gpsPings->record(new GpsPingRecord(
            $pingId,
            $session->id,
            $location,
            $accuracyInMeters,
            $withinGeofence,
            $this->clock->now(),
        ));

        return $session;
    }

    /**
     * @throws NotFoundException
     * @throws PresenceSessionAccessDenied
     */
    public function end(string $sessionId, string $requestingUserId): PresenceSession
    {
        $session = $this->findOrFail($sessionId);
        $this->assertOwnedBy($session, $requestingUserId);

        $session->end($this->clock);

        $this->persist($session);

        return $session;
    }

    /**
     * @throws NotFoundException
     */
    private function findOrFail(string $sessionId): PresenceSession
    {
        $session = $this->sessions->findById($sessionId);

        if ($session === null) {
            throw new NotFoundException("Presence session [{$sessionId}] not found.");
        }

        return $session;
    }

    /**
     * @throws PresenceSessionAccessDenied
     */
    private function assertOwnedBy(PresenceSession $session, string $requestingUserId): void
    {
        if ($session->sellerId !== $requestingUserId) {
            throw PresenceSessionAccessDenied::forSessionId($session->id);
        }
    }

    private function persist(PresenceSession $session): void
    {
        $this->sessions->save($session);

        foreach ($session->releaseEvents() as $event) {
            $this->events->publish($event);
        }
    }
}
