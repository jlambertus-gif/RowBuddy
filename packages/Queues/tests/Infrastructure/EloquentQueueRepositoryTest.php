<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentQueueRepository;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('queues', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('category');
        $table->string('jurisdiction_country', 2);
        $table->decimal('center_latitude', 10, 7);
        $table->decimal('center_longitude', 10, 7);
        $table->double('radius_meters');
        $table->string('authorship');
        $table->string('organizer_reference')->nullable();
        $table->string('status');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('queues');
});

it('round-trips an admin-curated published queue through the repository', function () {
    $repository = new EloquentQueueRepository;

    $queue = Queue::publishDirectly(
        id: 'queue-1',
        category: 'concert',
        jurisdictionCountry: 'US',
        geofence: new Geofence(new GeoPoint(32.7157, -117.1611), 200),
        organizerReference: 'venue-42',
    );

    $repository->save($queue);
    $found = $repository->findById('queue-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('queue-1')
        ->and($found->category)->toBe('concert')
        ->and($found->jurisdictionCountry)->toBe('US')
        ->and($found->status())->toBe(QueueStatus::Published)
        ->and($found->authorship)->toBe($queue->authorship)
        ->and($found->organizerReference)->toBe('venue-42')
        ->and($found->geofence->center->latitude)->toEqualWithDelta(32.7157, 0.0001)
        ->and($found->geofence->center->longitude)->toEqualWithDelta(-117.1611, 0.0001)
        ->and($found->geofence->radiusInMeters)->toEqualWithDelta(200.0, 0.001)
        ->and($found->releaseEvents())->toBe([]);
});

it('round-trips a pending user-submitted queue with a null organizer reference', function () {
    $repository = new EloquentQueueRepository;

    $queue = Queue::submitForApproval(
        id: 'queue-2',
        category: 'concert',
        jurisdictionCountry: 'US',
        geofence: new Geofence(new GeoPoint(0, 0), 100),
        submittedByUserId: 'user-9',
        clock: new FrozenClock,
    );

    $repository->save($queue);
    $found = $repository->findById('queue-2');

    expect($found->status())->toBe(QueueStatus::Pending)
        ->and($found->organizerReference)->toBeNull();
});

it('returns null when the queue does not exist', function () {
    expect((new EloquentQueueRepository)->findById('missing'))->toBeNull();
});

it('persists a status transition made after reloading from the repository', function () {
    $repository = new EloquentQueueRepository;

    $queue = Queue::submitForApproval('queue-3', 'concert', 'US', new Geofence(new GeoPoint(0, 0), 100), 'user-9', new FrozenClock);
    $repository->save($queue);

    $reloaded = $repository->findById('queue-3');
    $reloaded->approve('admin-1', new FrozenClock);
    $repository->save($reloaded);

    expect($repository->findById('queue-3')->status())->toBe(QueueStatus::Approved);
});
