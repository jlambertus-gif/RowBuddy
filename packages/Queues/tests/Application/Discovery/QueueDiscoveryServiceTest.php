<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\Discovery\QueueDiscoveryService;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\Tests\Fakes\InMemoryQueueDiscoveryRepository;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

function aPublishedQueueAt(string $id, float $latitude, float $longitude): Queue
{
    return Queue::publishDirectly($id, 'concert', 'US', new Geofence(new GeoPoint($latitude, $longitude), 100), 'venue-1');
}

it('ranks results by distance to the search point, nearest first', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $searchPoint = new GeoPoint(0.0, 0.0);

    $far = aPublishedQueueAt('far', 1.0, 1.0);
    $near = aPublishedQueueAt('near', 0.01, 0.01);
    $discovery->willReturn([$far, $near]);

    $service = new QueueDiscoveryService($discovery);

    $page = $service->discover($searchPoint, 1, 20);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[0]->queue->id)->toBe('near')
        ->and($page->items[1]->queue->id)->toBe('far')
        ->and($page->items[0]->distanceInMeters)->toBeLessThan($page->items[1]->distanceInMeters);
});

it('paginates in memory, slicing the ranked results', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $searchPoint = new GeoPoint(0.0, 0.0);

    $discovery->willReturn([
        aPublishedQueueAt('a', 0.01, 0.0),
        aPublishedQueueAt('b', 0.02, 0.0),
        aPublishedQueueAt('c', 0.03, 0.0),
    ]);

    $service = new QueueDiscoveryService($discovery);

    $firstPage = $service->discover($searchPoint, 1, 2);
    expect($firstPage->items)->toHaveCount(2)
        ->and($firstPage->total)->toBe(3)
        ->and($firstPage->hasMore)->toBeTrue()
        ->and($firstPage->items[0]->queue->id)->toBe('a')
        ->and($firstPage->items[1]->queue->id)->toBe('b');

    $secondPage = $service->discover($searchPoint, 2, 2);
    expect($secondPage->items)->toHaveCount(1)
        ->and($secondPage->hasMore)->toBeFalse()
        ->and($secondPage->items[0]->queue->id)->toBe('c');
});

it('returns an empty page when nothing matches', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $discovery->willReturn([]);

    $service = new QueueDiscoveryService($discovery);

    $page = $service->discover(new GeoPoint(0.0, 0.0), 1, 20);

    expect($page->items)->toBe([])
        ->and($page->total)->toBe(0)
        ->and($page->hasMore)->toBeFalse();
});
