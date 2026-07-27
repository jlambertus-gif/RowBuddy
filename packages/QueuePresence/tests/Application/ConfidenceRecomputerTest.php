<?php

declare(strict_types=1);

use RowBuddy\QueuePresence\Application\ConfidenceRecomputer;
use RowBuddy\QueuePresence\Events\PresenceConfidenceComputed;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\Scoring\ConfidenceScorer;
use RowBuddy\QueuePresence\Tests\Fakes\InMemoryConfidenceScoreRepository;
use RowBuddy\QueuePresence\Tests\Fakes\InMemoryEvidencePhotoRepository;
use RowBuddy\QueuePresence\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\QueuePresence\Tests\Fakes\RecordingGpsPingRepository;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceTier;
use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

function makeConfidenceRecomputer(
    RecordingGpsPingRepository $gpsPings,
    InMemoryEvidencePhotoRepository $evidencePhotos,
    InMemoryConfidenceScoreRepository $scores,
    RecordingDomainEventPublisher $events,
    ?FrozenClock $clock = null,
): ConfidenceRecomputer {
    return new ConfidenceRecomputer(
        $gpsPings,
        $evidencePhotos,
        $scores,
        new ConfidenceScorer,
        $events,
        $clock ?? new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00')),
    );
}

it('scores an untouched session as unverified and does not publish an event', function () {
    $scores = new InMemoryConfidenceScoreRepository;
    $events = new RecordingDomainEventPublisher;
    $recomputer = makeConfidenceRecomputer(new RecordingGpsPingRepository, new InMemoryEvidencePhotoRepository, $scores, $events);
    $session = PresenceSession::start('session-1', 'queue-1', 'seller-1', new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00')));

    $record = $recomputer->recompute($session);

    expect($record->points)->toBe(0)
        ->and($record->tier)->toBe(ConfidenceTier::Unverified)
        ->and($scores->recorded)->toHaveCount(1)
        ->and($events->published)->toBe([]);
});

it('publishes a confidence-computed event the first time the tier leaves unverified', function () {
    $gpsPings = new RecordingGpsPingRepository;
    $gpsPings->record(new GpsPingRecord('ping-1', 'session-2', new GeoPoint(32.7157, -117.1611), 60.0, true, new DateTimeImmutable));
    $scores = new InMemoryConfidenceScoreRepository;
    $events = new RecordingDomainEventPublisher;
    $recomputer = makeConfidenceRecomputer($gpsPings, new InMemoryEvidencePhotoRepository, $scores, $events);
    $session = PresenceSession::start('session-2', 'queue-1', 'seller-1', new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00')));

    $record = $recomputer->recompute($session);

    expect($record->tier)->toBe(ConfidenceTier::LocationVerified)
        ->and($events->published)->toHaveCount(1);
    $event = $events->published[0];
    expect($event)->toBeInstanceOf(PresenceConfidenceComputed::class)
        ->and($event->payload())->toBe([
            'presence_session_id' => 'session-2',
            'previous_tier' => null,
            'new_tier' => 'location_verified',
            'points' => 40,
        ]);
});

it('does not publish another event when a later recomputation stays in the same tier', function () {
    $gpsPings = new RecordingGpsPingRepository;
    $gpsPings->record(new GpsPingRecord('ping-2', 'session-3', new GeoPoint(32.7157, -117.1611), 60.0, true, new DateTimeImmutable));
    $scores = new InMemoryConfidenceScoreRepository;
    $events = new RecordingDomainEventPublisher;
    $recomputer = makeConfidenceRecomputer($gpsPings, new InMemoryEvidencePhotoRepository, $scores, $events);
    $session = PresenceSession::start('session-3', 'queue-1', 'seller-1', new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00')));

    $recomputer->recompute($session); // 40 points, LocationVerified — publishes once

    // A second, better-accuracy ping still lands in LocationVerified (still well under the 140 threshold).
    $gpsPings->record(new GpsPingRecord('ping-3', 'session-3', new GeoPoint(32.7157, -117.1611), 15.0, true, new DateTimeImmutable));
    $record = $recomputer->recompute($session);

    expect($record->tier)->toBe(ConfidenceTier::LocationVerified)
        ->and($scores->recorded)->toHaveCount(2)
        ->and($events->published)->toHaveCount(1);
});

it('publishes a second event when a later recomputation crosses into a higher tier', function () {
    $gpsPings = new RecordingGpsPingRepository;
    $gpsPings->record(new GpsPingRecord('ping-4', 'session-4', new GeoPoint(32.7157, -117.1611), 60.0, true, new DateTimeImmutable));
    $evidencePhotos = new InMemoryEvidencePhotoRepository;
    $scores = new InMemoryConfidenceScoreRepository;
    $events = new RecordingDomainEventPublisher;
    $recomputer = makeConfidenceRecomputer($gpsPings, $evidencePhotos, $scores, $events);
    $session = PresenceSession::start('session-4', 'queue-1', 'seller-1', new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00')));

    $recomputer->recompute($session); // LocationVerified

    $evidencePhotos->record(new EvidencePhotoRecord('photo-1', 'session-4', 'ref-1', 'image/jpeg', 1000, new DateTimeImmutable));
    $record = $recomputer->recompute($session); // now EvidenceVerified

    expect($record->tier)->toBe(ConfidenceTier::EvidenceVerified)
        ->and($events->published)->toHaveCount(2);
    expect($events->published[1]->payload())->toBe([
        'presence_session_id' => 'session-4',
        'previous_tier' => 'location_verified',
        'new_tier' => 'evidence_verified',
        'points' => 140,
    ]);
});

it('computes presence duration from start to now for an active session', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-08-24 10:10:00'));
    $gpsPings = new RecordingGpsPingRepository;
    $gpsPings->record(new GpsPingRecord('ping-5', 'session-5', new GeoPoint(32.7157, -117.1611), 60.0, true, new DateTimeImmutable));
    $scores = new InMemoryConfidenceScoreRepository;
    $recomputer = makeConfidenceRecomputer($gpsPings, new InMemoryEvidencePhotoRepository, $scores, new RecordingDomainEventPublisher, $clock);
    $session = PresenceSession::start('session-5', 'queue-1', 'seller-1', new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00')));

    // 10 minutes elapsed since start, per the recomputer's own clock -> long-duration bonus applies.
    $record = $recomputer->recompute($session);

    expect($record->points)->toBe(60); // 40 (within-geofence) + 20 (>=600s duration)
});

it('freezes presence duration at the moment a session ended, not still growing afterward', function () {
    $startClock = new FrozenClock(new DateTimeImmutable('2026-08-24 10:00:00'));
    $session = PresenceSession::start('session-6', 'queue-1', 'seller-1', $startClock);
    $session->end(new FrozenClock(new DateTimeImmutable('2026-08-24 10:01:00'))); // ended after only 60s

    $gpsPings = new RecordingGpsPingRepository;
    $gpsPings->record(new GpsPingRecord('ping-6', 'session-6', new GeoPoint(32.7157, -117.1611), 60.0, true, new DateTimeImmutable));
    $scores = new InMemoryConfidenceScoreRepository;
    // Recomputing "long after" the session ended must not count that elapsed time.
    $recomputer = makeConfidenceRecomputer($gpsPings, new InMemoryEvidencePhotoRepository, $scores, new RecordingDomainEventPublisher, new FrozenClock(new DateTimeImmutable('2026-08-25 00:00:00')));

    $record = $recomputer->recompute($session);

    expect($record->points)->toBe(40); // within-geofence only — 60s of duration earns no bonus
});
