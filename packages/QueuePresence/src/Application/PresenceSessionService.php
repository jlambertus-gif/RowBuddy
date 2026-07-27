<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Application;

use RowBuddy\QueuePresence\Contracts\DomainEventPublisher;
use RowBuddy\QueuePresence\Contracts\EvidencePhotoRepository;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;
use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\Contracts\ImageMetadataStripper;
use RowBuddy\QueuePresence\Contracts\PresenceSessionRepository;
use RowBuddy\QueuePresence\Contracts\QueueGeofenceLookup;
use RowBuddy\QueuePresence\Exceptions\EvidenceStorageFailed;
use RowBuddy\QueuePresence\Exceptions\InvalidEvidencePhoto;
use RowBuddy\QueuePresence\Exceptions\PresenceQueueUnavailable;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceScoreRecord;
use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;
use RowBuddy\QueuePresence\ValueObjects\TemporaryEvidenceUrl;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Orchestrates presence capture: starting a session, recording a GPS ping
 * or evidence photo against it, and ending it. Depends only on
 * domain-facing ports — no Eloquent, no Laravel container — so every
 * business decision here is testable with plain in-memory fakes, no
 * database.
 *
 * Ownership is enforced here, not left to the HTTP layer or a database
 * constraint: a request naming a session that belongs to a different
 * seller is a domain-level authorization failure
 * ({@see PresenceSessionAccessDenied}), the same way the legal gate is
 * enforced in Queues' application services rather than its controllers.
 *
 * Every GPS ping and evidence photo triggers a {@see ConfidenceRecomputer}
 * recomputation (ADR-008) — the resulting score is always persisted, but
 * it's only published as a domain event (and therefore audited) when the
 * tier materially changes.
 */
final class PresenceSessionService
{
    private const EVIDENCE_URL_TTL_SECONDS = 300;

    public function __construct(
        private readonly PresenceSessionRepository $sessions,
        private readonly GpsPingRepository $gpsPings,
        private readonly EvidencePhotoRepository $evidencePhotos,
        private readonly QueueGeofenceLookup $queueGeofences,
        private readonly EvidenceStorage $evidenceStorage,
        private readonly ImageMetadataStripper $metadataStripper,
        private readonly ConfidenceRecomputer $confidenceRecomputer,
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

        $this->confidenceRecomputer->recompute($session);

        return $session;
    }

    /**
     * @throws NotFoundException
     * @throws PresenceSessionAccessDenied
     * @throws PresenceSessionNotActive
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
     * @throws PresenceSessionAccessDenied
     * @throws PresenceSessionNotActive
     * @throws InvalidEvidencePhoto
     * @throws EvidenceStorageFailed
     */
    public function recordEvidencePhoto(
        string $photoId,
        string $sessionId,
        string $requestingUserId,
        string $imageContents,
        string $mimeType,
    ): PresenceSession {
        $session = $this->findOrFail($sessionId);
        $this->assertOwnedBy($session, $requestingUserId);

        if ($session->status() !== PresenceSessionStatus::Active) {
            throw PresenceSessionNotActive::forSessionId($session->id);
        }

        // Stripped and stored before touching the aggregate: if the
        // session turned out not to be active, we'd rather fail before
        // spending the image-processing work and a storage write than
        // after, which would otherwise leave an orphaned file behind.
        $strippedContents = $this->metadataStripper->strip($imageContents);
        $storageReference = $this->evidenceStorage->store($session->id, $strippedContents);

        $session->recordEvidencePhoto($storageReference, $this->clock);

        $this->persist($session);

        $this->evidencePhotos->record(new EvidencePhotoRecord(
            $photoId,
            $session->id,
            $storageReference,
            $mimeType,
            strlen($strippedContents),
            $this->clock->now(),
        ));

        $this->confidenceRecomputer->recompute($session);

        return $session;
    }

    /**
     * @throws NotFoundException
     * @throws PresenceSessionAccessDenied
     */
    public function evidencePhotoUrl(string $sessionId, string $photoId, string $requestingUserId): TemporaryEvidenceUrl
    {
        $session = $this->findOrFail($sessionId);
        $this->assertOwnedBy($session, $requestingUserId);

        $photo = $this->evidencePhotos->findById($photoId);

        if ($photo === null || $photo->presenceSessionId !== $session->id) {
            throw new NotFoundException("Evidence photo [{$photoId}] not found.");
        }

        $expiresAt = $this->clock->now()->modify('+'.self::EVIDENCE_URL_TTL_SECONDS.' seconds');

        return new TemporaryEvidenceUrl(
            $this->evidenceStorage->temporaryUrl($photo->storageReference, $expiresAt),
            $expiresAt,
        );
    }

    /**
     * The current confidence score for a session, for display after any
     * action — null if no signal has been recorded yet (the UI treats
     * that as Unverified/0, there is no row to default to).
     *
     * @throws NotFoundException
     * @throws PresenceSessionAccessDenied
     */
    public function currentConfidenceScore(string $sessionId, string $requestingUserId): ?ConfidenceScoreRecord
    {
        $session = $this->findOrFail($sessionId);
        $this->assertOwnedBy($session, $requestingUserId);

        return $this->confidenceRecomputer->latestScoreFor($session->id);
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
