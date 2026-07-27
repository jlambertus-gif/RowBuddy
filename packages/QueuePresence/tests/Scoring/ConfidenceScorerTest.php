<?php

declare(strict_types=1);

use RowBuddy\QueuePresence\Scoring\ConfidenceScorer;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceTier;

it('scores zero and is unverified when there is no signal at all', function () {
    $score = (new ConfidenceScorer)->score(
        hasWithinGeofencePing: false,
        bestGpsAccuracyInMeters: null,
        presenceDurationInSeconds: 0,
        hasEvidencePhoto: false,
    );

    expect($score->points)->toBe(0)
        ->and($score->tier)->toBe(ConfidenceTier::Unverified);
});

it('reaches location verified from a bare within-geofence ping alone', function () {
    $score = (new ConfidenceScorer)->score(
        hasWithinGeofencePing: true,
        bestGpsAccuracyInMeters: 500.0,
        presenceDurationInSeconds: 0,
        hasEvidencePhoto: false,
    );

    expect($score->points)->toBe(40)
        ->and($score->tier)->toBe(ConfidenceTier::LocationVerified);
});

it('never reaches evidence verified from GPS signals alone, no matter how strong', function () {
    $score = (new ConfidenceScorer)->score(
        hasWithinGeofencePing: true,
        bestGpsAccuracyInMeters: 5.0,
        presenceDurationInSeconds: 3600,
        hasEvidencePhoto: false,
    );

    expect($score->points)->toBe(80)
        ->and($score->tier)->toBe(ConfidenceTier::LocationVerified);
});

it('contributes nothing for an evidence photo when no within-geofence ping exists', function () {
    $score = (new ConfidenceScorer)->score(
        hasWithinGeofencePing: false,
        bestGpsAccuracyInMeters: null,
        presenceDurationInSeconds: 3600,
        hasEvidencePhoto: true,
    );

    expect($score->points)->toBe(0)
        ->and($score->tier)->toBe(ConfidenceTier::Unverified);
});

it('reaches evidence verified from the minimum within-geofence ping plus a photo', function () {
    $score = (new ConfidenceScorer)->score(
        hasWithinGeofencePing: true,
        bestGpsAccuracyInMeters: null,
        presenceDurationInSeconds: 0,
        hasEvidencePhoto: true,
    );

    expect($score->points)->toBe(140)
        ->and($score->tier)->toBe(ConfidenceTier::EvidenceVerified);
});

it('reaches the maximum score with every signal at its best', function () {
    $score = (new ConfidenceScorer)->score(
        hasWithinGeofencePing: true,
        bestGpsAccuracyInMeters: 1.0,
        presenceDurationInSeconds: 10_000,
        hasEvidencePhoto: true,
    );

    expect($score->points)->toBe(180)
        ->and($score->tier)->toBe(ConfidenceTier::EvidenceVerified);
});

it('scores accuracy at the excellent, acceptable, and no-bonus boundaries', function () {
    $scorer = new ConfidenceScorer;

    $excellent = $scorer->score(true, 20.0, 0, false);
    $acceptable = $scorer->score(true, 50.0, 0, false);
    $tooImprecise = $scorer->score(true, 50.1, 0, false);

    expect($excellent->points)->toBe(60)
        ->and($acceptable->points)->toBe(50)
        ->and($tooImprecise->points)->toBe(40);
});

it('scores presence duration at the long, moderate, and no-bonus boundaries', function () {
    $scorer = new ConfidenceScorer;

    $long = $scorer->score(true, null, 600, false);
    $moderate = $scorer->score(true, null, 120, false);
    $short = $scorer->score(true, null, 119, false);

    expect($long->points)->toBe(60)
        ->and($moderate->points)->toBe(50)
        ->and($short->points)->toBe(40);
});
