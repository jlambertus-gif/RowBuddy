<?php

declare(strict_types=1);

use RowBuddy\Queues\Events\QueueApproved;
use RowBuddy\Queues\Events\QueuePublished;
use RowBuddy\Queues\Events\QueueRejected;
use RowBuddy\Queues\Events\QueueSubmittedForApproval;
use RowBuddy\Queues\Exceptions\InvalidQueueStatusTransition;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueAuthorship;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

function aGeofence(): Geofence
{
    return new Geofence(new GeoPoint(32.7157, -117.1611), 200);
}

it('publishes an admin-curated queue directly, with no domain event', function () {
    $queue = Queue::publishDirectly(
        id: 'queue-1',
        category: 'concert',
        jurisdictionCountry: 'US',
        geofence: aGeofence(),
        organizerReference: 'venue-42',
    );

    expect($queue->status())->toBe(QueueStatus::Published)
        ->and($queue->authorship)->toBe(QueueAuthorship::AdminCurated)
        ->and($queue->releaseEvents())->toBe([]);
});

it('starts a user-submitted queue as pending and raises a submission event', function () {
    $queue = Queue::submitForApproval(
        id: 'queue-2',
        category: 'concert',
        jurisdictionCountry: 'US',
        geofence: aGeofence(),
        submittedByUserId: 'user-9',
        clock: new FrozenClock,
    );

    expect($queue->status())->toBe(QueueStatus::Pending)
        ->and($queue->authorship)->toBe(QueueAuthorship::UserSubmitted);

    $events = $queue->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(QueueSubmittedForApproval::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $queue = Queue::submitForApproval('queue-3', 'concert', 'US', aGeofence(), 'user-9', new FrozenClock);

    $queue->releaseEvents();

    expect($queue->releaseEvents())->toBe([]);
});

it('moves a pending queue through approved to published', function () {
    $queue = Queue::submitForApproval('queue-4', 'concert', 'US', aGeofence(), 'user-9', new FrozenClock);
    $queue->releaseEvents();

    $queue->approve('admin-1', new FrozenClock);
    expect($queue->status())->toBe(QueueStatus::Approved);
    $events = $queue->releaseEvents();
    expect($events)->toHaveCount(1)->and($events[0])->toBeInstanceOf(QueueApproved::class);

    $queue->publish(new FrozenClock);
    expect($queue->status())->toBe(QueueStatus::Published);
    $events = $queue->releaseEvents();
    expect($events)->toHaveCount(1)->and($events[0])->toBeInstanceOf(QueuePublished::class);
});

it('rejects a pending queue with a reason', function () {
    $queue = Queue::submitForApproval('queue-5', 'concert', 'US', aGeofence(), 'user-9', new FrozenClock);
    $queue->releaseEvents();

    $queue->reject('admin-1', 'Restricted category: medical.', new FrozenClock);

    expect($queue->status())->toBe(QueueStatus::Rejected);
    $events = $queue->releaseEvents();
    expect($events)->toHaveCount(1)->and($events[0])->toBeInstanceOf(QueueRejected::class);
});

it('cannot publish a pending queue that has not been approved', function () {
    $queue = Queue::submitForApproval('queue-6', 'concert', 'US', aGeofence(), 'user-9', new FrozenClock);

    $queue->publish(new FrozenClock);
})->throws(InvalidQueueStatusTransition::class);

it('cannot approve a queue that is not pending', function () {
    $queue = Queue::publishDirectly('queue-7', 'concert', 'US', aGeofence(), 'venue-42');

    $queue->approve('admin-1', new FrozenClock);
})->throws(InvalidQueueStatusTransition::class);

it('cannot reject a queue once it has already been approved', function () {
    $queue = Queue::submitForApproval('queue-8', 'concert', 'US', aGeofence(), 'user-9', new FrozenClock);
    $queue->approve('admin-1', new FrozenClock);

    $queue->reject('admin-1', 'too late', new FrozenClock);
})->throws(InvalidQueueStatusTransition::class);

it('cannot re-approve an already-published queue', function () {
    $queue = Queue::submitForApproval('queue-9', 'concert', 'US', aGeofence(), 'user-9', new FrozenClock);
    $queue->approve('admin-1', new FrozenClock);
    $queue->publish(new FrozenClock);

    $queue->approve('admin-1', new FrozenClock);
})->throws(InvalidQueueStatusTransition::class);
