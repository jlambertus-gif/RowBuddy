<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\Discovery\CoverageAreaAssigner;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\Tests\Fakes\InMemoryQueueDiscoveryRepository;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

it('assigns a coverage area approximating the geofence when the queue is published', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $assigner = new CoverageAreaAssigner($discovery);

    $queue = Queue::publishDirectly('queue-1', 'concert', 'US', new Geofence(new GeoPoint(32.7157, -117.1611), 300), 'venue-1');

    $assigner->assignDefaultCoverageArea($queue);

    expect($discovery->assignedCoverageAreas)->toHaveKey('queue-1');
    $coverageArea = $discovery->assignedCoverageAreas['queue-1'];
    $ring = $coverageArea->polygons[0]->exteriorRing;
    foreach (array_slice($ring->points, 0, -1) as $vertex) {
        expect((new GeoPoint(32.7157, -117.1611))->distanceInMetersTo($vertex))->toEqualWithDelta(300.0, 1.0);
    }
});

it('does nothing when the queue is pending', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $assigner = new CoverageAreaAssigner($discovery);

    $queue = Queue::submitForApproval('queue-2', 'concert', 'US', new Geofence(new GeoPoint(0, 0), 100), 'user-9', new FrozenClock);

    $assigner->assignDefaultCoverageArea($queue);

    expect($discovery->assignedCoverageAreas)->toBe([]);
});

it('does nothing when the queue is approved but not yet published', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $assigner = new CoverageAreaAssigner($discovery);

    $queue = Queue::submitForApproval('queue-3', 'concert', 'US', new Geofence(new GeoPoint(0, 0), 100), 'user-9', new FrozenClock);
    $queue->approve('admin-1', new FrozenClock);

    $assigner->assignDefaultCoverageArea($queue);

    expect($discovery->assignedCoverageAreas)->toBe([]);
});

it('does nothing when the queue is rejected', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $assigner = new CoverageAreaAssigner($discovery);

    $queue = Queue::submitForApproval('queue-4', 'concert', 'US', new Geofence(new GeoPoint(0, 0), 100), 'user-9', new FrozenClock);
    $queue->reject('admin-1', 'no', new FrozenClock);

    $assigner->assignDefaultCoverageArea($queue);

    expect($discovery->assignedCoverageAreas)->toBe([]);
});
