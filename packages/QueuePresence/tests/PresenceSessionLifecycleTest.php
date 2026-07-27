<?php

declare(strict_types=1);

use RowBuddy\QueuePresence\Events\EvidencePhotoRecorded;
use RowBuddy\QueuePresence\Events\GpsPingRecorded;
use RowBuddy\QueuePresence\Events\PresenceSessionEnded;
use RowBuddy\QueuePresence\Events\PresenceSessionStarted;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('starts a presence session as active and raises a started event', function () {
    $startedAt = new DateTimeImmutable('2026-08-01 10:00:00');
    $session = PresenceSession::start('session-1', 'queue-1', 'seller-1', new FrozenClock($startedAt));

    expect($session->status())->toBe(PresenceSessionStatus::Active)
        ->and($session->queueId)->toBe('queue-1')
        ->and($session->sellerId)->toBe('seller-1')
        ->and($session->startedAt)->toEqual($startedAt);

    $events = $session->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PresenceSessionStarted::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $session = PresenceSession::start('session-2', 'queue-1', 'seller-1', new FrozenClock);

    $session->releaseEvents();

    expect($session->releaseEvents())->toBe([]);
});

it('records a GPS ping while active', function () {
    $session = PresenceSession::start('session-3', 'queue-1', 'seller-1', new FrozenClock);
    $session->releaseEvents();

    $session->recordGpsPing(32.7157, -117.1611, 12.5, new FrozenClock);

    $events = $session->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(GpsPingRecorded::class)
        ->and($events[0]->payload())->toBe([
            'presence_session_id' => 'session-3',
            'latitude' => 32.7157,
            'longitude' => -117.1611,
            'accuracy_in_meters' => 12.5,
        ]);
});

it('records an evidence photo while active', function () {
    $session = PresenceSession::start('session-4', 'queue-1', 'seller-1', new FrozenClock);
    $session->releaseEvents();

    $session->recordEvidencePhoto('evidence-ref-1', new FrozenClock);

    $events = $session->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(EvidencePhotoRecorded::class)
        ->and($events[0]->payload())->toBe([
            'presence_session_id' => 'session-4',
            'evidence_reference' => 'evidence-ref-1',
        ]);
});

it('ends an active session and raises an ended event', function () {
    $session = PresenceSession::start('session-5', 'queue-1', 'seller-1', new FrozenClock);
    $session->releaseEvents();

    $session->end(new FrozenClock);

    expect($session->status())->toBe(PresenceSessionStatus::Ended);
    $events = $session->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PresenceSessionEnded::class);
});

it('cannot record a GPS ping once the session has ended', function () {
    $session = PresenceSession::start('session-6', 'queue-1', 'seller-1', new FrozenClock);
    $session->end(new FrozenClock);

    $session->recordGpsPing(32.7157, -117.1611, 12.5, new FrozenClock);
})->throws(PresenceSessionNotActive::class);

it('cannot record an evidence photo once the session has ended', function () {
    $session = PresenceSession::start('session-7', 'queue-1', 'seller-1', new FrozenClock);
    $session->end(new FrozenClock);

    $session->recordEvidencePhoto('evidence-ref-1', new FrozenClock);
})->throws(PresenceSessionNotActive::class);

it('cannot end an already-ended session', function () {
    $session = PresenceSession::start('session-8', 'queue-1', 'seller-1', new FrozenClock);
    $session->end(new FrozenClock);

    $session->end(new FrozenClock);
})->throws(PresenceSessionNotActive::class);
