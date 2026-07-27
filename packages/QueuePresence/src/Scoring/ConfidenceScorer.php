<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Scoring;

use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceScore;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceTier;

/**
 * The v1, rules-based confidence-scoring engine (ADR-008,
 * docs/decisions/008-confidence-scoring-model-v1.md — see that ADR for the
 * full rationale behind every weight and threshold below). Deliberately
 * dependency-free: it takes only already-computed primitives, never a
 * {@see PresenceSession}, repository, or clock —
 * the caller is responsible for gathering those facts and computing
 * elapsed duration.
 */
final class ConfidenceScorer
{
    private const WITHIN_GEOFENCE_POINTS = 40;

    private const ACCURACY_EXCELLENT_POINTS = 20;

    private const ACCURACY_EXCELLENT_METERS = 20.0;

    private const ACCURACY_ACCEPTABLE_POINTS = 10;

    private const ACCURACY_ACCEPTABLE_METERS = 50.0;

    private const DURATION_LONG_POINTS = 20;

    private const DURATION_LONG_SECONDS = 600;

    private const DURATION_MODERATE_POINTS = 10;

    private const DURATION_MODERATE_SECONDS = 120;

    private const EVIDENCE_PHOTO_POINTS = 100;

    private const LOCATION_VERIFIED_THRESHOLD = 40;

    private const EVIDENCE_VERIFIED_THRESHOLD = 140;

    public function score(
        bool $hasWithinGeofencePing,
        ?float $bestGpsAccuracyInMeters,
        int $presenceDurationInSeconds,
        bool $hasEvidencePhoto,
    ): ConfidenceScore {
        $points = 0;

        if ($hasWithinGeofencePing) {
            $points += self::WITHIN_GEOFENCE_POINTS;
            $points += $this->accuracyPoints($bestGpsAccuracyInMeters);
            $points += $this->durationPoints($presenceDurationInSeconds);

            if ($hasEvidencePhoto) {
                $points += self::EVIDENCE_PHOTO_POINTS;
            }
        }

        return new ConfidenceScore($points, $this->tierFor($points));
    }

    private function accuracyPoints(?float $bestGpsAccuracyInMeters): int
    {
        if ($bestGpsAccuracyInMeters === null) {
            return 0;
        }

        if ($bestGpsAccuracyInMeters <= self::ACCURACY_EXCELLENT_METERS) {
            return self::ACCURACY_EXCELLENT_POINTS;
        }

        if ($bestGpsAccuracyInMeters <= self::ACCURACY_ACCEPTABLE_METERS) {
            return self::ACCURACY_ACCEPTABLE_POINTS;
        }

        return 0;
    }

    private function durationPoints(int $presenceDurationInSeconds): int
    {
        if ($presenceDurationInSeconds >= self::DURATION_LONG_SECONDS) {
            return self::DURATION_LONG_POINTS;
        }

        if ($presenceDurationInSeconds >= self::DURATION_MODERATE_SECONDS) {
            return self::DURATION_MODERATE_POINTS;
        }

        return 0;
    }

    private function tierFor(int $points): ConfidenceTier
    {
        if ($points >= self::EVIDENCE_VERIFIED_THRESHOLD) {
            return ConfidenceTier::EvidenceVerified;
        }

        if ($points >= self::LOCATION_VERIFIED_THRESHOLD) {
            return ConfidenceTier::LocationVerified;
        }

        return ConfidenceTier::Unverified;
    }
}
