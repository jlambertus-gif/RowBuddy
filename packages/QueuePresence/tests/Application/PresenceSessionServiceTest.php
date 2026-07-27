<?php

declare(strict_types=1);

use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\Events\GpsPingRecorded;
use RowBuddy\QueuePresence\Events\PresenceSessionEnded;
use RowBuddy\QueuePresence\Events\PresenceSessionStarted;
use RowBuddy\QueuePresence\Exceptions\DuplicateActivePresenceSession;
use RowBuddy\QueuePresence\Exceptions\PresenceQueueUnavailable;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\QueuePresence\Tests\Fakes\InMemoryPresenceSessionRepository;
use RowBuddy\QueuePresence\Tests\Fakes\InMemoryQueueGeofenceLookup;
use RowBuddy\QueuePresence\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\QueuePresence\Tests\Fakes\RecordingGpsPingRepository;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * This entire suite runs without Laravel, without a database, and without
 * Eloquent: every collaborator is a plain in-memory fake implementing the
 * same domain-facing interface the real adapters implement.
 */
function makePresenceSessionService(
    InMemoryPresenceSessionRepository $sessions,
    RecordingGpsPingRepository $gpsPings,
    InMemoryQueueGeofenceLookup $queueGeofences,
    RecordingDomainEventPublisher $events,
): PresenceSessionService {
    return new PresenceSessionService(
        $sessions,
        $gpsPings,
        $queueGeofences,
        $events,
        new FrozenClock(new DateTimeImmutable('2026-08-10 10:00:00')),
    );
}

function aTestQueueGeofence(): Geofence
{
    return new Geofence(new GeoPoint(32.7157, -117.1611), 200);
}

it('starts a presence session for a published queue and publishes its domain event', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());
    $events = new RecordingDomainEventPublisher;

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, $queueGeofences, $events);

    $session = $service->start('session-1', 'queue-1', 'seller-1');

    expect($session->status())->toBe(PresenceSessionStatus::Active)
        ->and($sessions->findById('session-1'))->toBe($session)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(PresenceSessionStarted::class);
});

it('refuses to start a session for a queue that is not published', function () {
    $sessions = new InMemoryPresenceSessionRepository;

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, new InMemoryQueueGeofenceLookup, new RecordingDomainEventPublisher);

    expect(fn () => $service->start('session-2', 'queue-missing', 'seller-1'))
        ->toThrow(PresenceQueueUnavailable::class);

    expect($sessions->findById('session-2'))->toBeNull();
});

it('propagates the duplicate-active-session failure from the repository', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, $queueGeofences, new RecordingDomainEventPublisher);

    $service->start('session-3', 'queue-1', 'seller-1');

    expect(fn () => $service->start('session-4', 'queue-1', 'seller-1'))
        ->toThrow(DuplicateActivePresenceSession::class);
});

it('records a within-geofence GPS ping and publishes its domain event', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());
    $gpsPings = new RecordingGpsPingRepository;
    $events = new RecordingDomainEventPublisher;

    $service = makePresenceSessionService($sessions, $gpsPings, $queueGeofences, $events);
    $service->start('session-5', 'queue-1', 'seller-1');

    $service->recordGpsPing('ping-1', 'session-5', 'seller-1', 32.7157, -117.1611, 12.5);

    expect($gpsPings->recorded)->toHaveCount(1)
        ->and($gpsPings->recorded[0]->withinGeofence)->toBeTrue()
        ->and($gpsPings->recorded[0]->accuracyInMeters)->toBe(12.5)
        ->and($events->published)->toHaveCount(2)
        ->and($events->published[1])->toBeInstanceOf(GpsPingRecorded::class);
});

it('records a GPS ping outside the geofence as such, without blocking it', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());
    $gpsPings = new RecordingGpsPingRepository;

    $service = makePresenceSessionService($sessions, $gpsPings, $queueGeofences, new RecordingDomainEventPublisher);
    $service->start('session-6', 'queue-1', 'seller-1');

    $service->recordGpsPing('ping-2', 'session-6', 'seller-1', 0.0, 0.0, 12.5);

    expect($gpsPings->recorded[0]->withinGeofence)->toBeFalse();
});

it('throws when recording a ping against an unknown session', function () {
    $service = makePresenceSessionService(new InMemoryPresenceSessionRepository, new RecordingGpsPingRepository, new InMemoryQueueGeofenceLookup, new RecordingDomainEventPublisher);

    expect(fn () => $service->recordGpsPing('ping-3', 'missing-session', 'seller-1', 0.0, 0.0, 10.0))
        ->toThrow(NotFoundException::class);
});

it('refuses to record a ping on another seller\'s session', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, $queueGeofences, new RecordingDomainEventPublisher);
    $service->start('session-7', 'queue-1', 'seller-1');

    expect(fn () => $service->recordGpsPing('ping-4', 'session-7', 'seller-intruder', 32.7157, -117.1611, 10.0))
        ->toThrow(PresenceSessionAccessDenied::class);
});

it('refuses to record a ping once the session has ended', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, $queueGeofences, new RecordingDomainEventPublisher);
    $service->start('session-8', 'queue-1', 'seller-1');
    $service->end('session-8', 'seller-1');

    expect(fn () => $service->recordGpsPing('ping-5', 'session-8', 'seller-1', 32.7157, -117.1611, 10.0))
        ->toThrow(PresenceSessionNotActive::class);
});

it('ends a session and publishes its domain event', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());
    $events = new RecordingDomainEventPublisher;

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, $queueGeofences, $events);
    $service->start('session-9', 'queue-1', 'seller-1');

    $session = $service->end('session-9', 'seller-1');

    expect($session->status())->toBe(PresenceSessionStatus::Ended)
        ->and($events->published)->toHaveCount(2)
        ->and($events->published[1])->toBeInstanceOf(PresenceSessionEnded::class);
});

it('throws when ending an unknown session', function () {
    $service = makePresenceSessionService(new InMemoryPresenceSessionRepository, new RecordingGpsPingRepository, new InMemoryQueueGeofenceLookup, new RecordingDomainEventPublisher);

    expect(fn () => $service->end('missing-session', 'seller-1'))
        ->toThrow(NotFoundException::class);
});

it('refuses to end another seller\'s session', function () {
    $sessions = new InMemoryPresenceSessionRepository;
    $queueGeofences = new InMemoryQueueGeofenceLookup;
    $queueGeofences->publish('queue-1', aTestQueueGeofence());

    $service = makePresenceSessionService($sessions, new RecordingGpsPingRepository, $queueGeofences, new RecordingDomainEventPublisher);
    $service->start('session-10', 'queue-1', 'seller-1');

    expect(fn () => $service->end('session-10', 'seller-intruder'))
        ->toThrow(PresenceSessionAccessDenied::class);

    expect($sessions->findById('session-10')->status())->toBe(PresenceSessionStatus::Active);
});
