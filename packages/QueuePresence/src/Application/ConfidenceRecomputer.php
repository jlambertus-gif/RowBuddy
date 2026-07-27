<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Application;

use Ramsey\Uuid\Uuid;
use RowBuddy\QueuePresence\Contracts\ConfidenceScoreRepository;
use RowBuddy\QueuePresence\Contracts\DomainEventPublisher;
use RowBuddy\QueuePresence\Contracts\EvidencePhotoRepository;
use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\Events\PresenceConfidenceComputed;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\Scoring\ConfidenceScorer;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceScoreRecord;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceTier;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Gathers the persisted signals ADR-008 approved for v1 (GPS
 * within-geofence + best accuracy, presence duration, evidence photo),
 * calls the pure {@see ConfidenceScorer}, and persists the result —
 * called by {@see PresenceSessionService} after recording a GPS ping or
 * an evidence photo (never after every ping alone would be scored
 * identically; this runs the real computation each time, but only
 * publishes {@see PresenceConfidenceComputed} when the tier actually
 * changes, so the audit trail isn't flooded with every point
 * fluctuation).
 *
 * Deliberately a separate collaborator rather than inlined into
 * PresenceSessionService: it is called from two places (after a ping,
 * after a photo) and bundles five collaborators of its own — the same
 * "extract once genuinely shared" reasoning as Queues'
 * CoverageAreaAssigner.
 */
final class ConfidenceRecomputer
{
    public function __construct(
        private readonly GpsPingRepository $gpsPings,
        private readonly EvidencePhotoRepository $evidencePhotos,
        private readonly ConfidenceScoreRepository $scores,
        private readonly ConfidenceScorer $scorer,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function recompute(PresenceSession $session): ConfidenceScoreRecord
    {
        $bestAccuracy = $this->gpsPings->bestAccuracyWithinGeofence($session->id);
        $hasEvidencePhoto = $this->evidencePhotos->hasAnyForSession($session->id);
        $duration = $session->presenceDurationInSeconds($this->clock);

        $score = $this->scorer->score(
            hasWithinGeofencePing: $bestAccuracy !== null,
            bestGpsAccuracyInMeters: $bestAccuracy,
            presenceDurationInSeconds: $duration,
            hasEvidencePhoto: $hasEvidencePhoto,
        );

        $previous = $this->scores->latestFor($session->id);

        $record = new ConfidenceScoreRecord(
            Uuid::uuid4()->toString(),
            $session->id,
            $score->points,
            $score->tier,
            $this->clock->now(),
        );

        $this->scores->record($record);

        if ($this->isMateriallyRelevant($previous?->tier, $score->tier)) {
            $this->events->publish(new PresenceConfidenceComputed(
                $this->clock,
                $session->id,
                $previous?->tier->value,
                $score->tier->value,
                $score->points,
            ));
        }

        return $record;
    }

    private function isMateriallyRelevant(?ConfidenceTier $previousTier, ConfidenceTier $newTier): bool
    {
        if ($previousTier === null) {
            return $newTier !== ConfidenceTier::Unverified;
        }

        return $previousTier !== $newTier;
    }
}
