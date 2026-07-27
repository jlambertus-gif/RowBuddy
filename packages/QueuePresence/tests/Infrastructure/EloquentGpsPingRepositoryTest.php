<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentGpsPingRepository;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\GpsPingModel;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;
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

    Capsule::schema()->create('presence_gps_pings', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('presence_session_id');
        $table->decimal('latitude', 10, 7);
        $table->decimal('longitude', 10, 7);
        $table->double('accuracy_meters');
        $table->boolean('within_geofence');
        $table->timestamp('recorded_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('presence_gps_pings');
});

it('records a GPS ping', function () {
    $repository = new EloquentGpsPingRepository;
    $recordedAt = new DateTimeImmutable('2026-08-10 10:05:00');

    $repository->record(new GpsPingRecord(
        'ping-1',
        'session-1',
        new GeoPoint(32.7157, -117.1611),
        12.5,
        true,
        $recordedAt,
    ));

    $model = GpsPingModel::query()->find('ping-1');

    expect($model)->not->toBeNull()
        ->and($model->presence_session_id)->toBe('session-1')
        ->and((float) $model->latitude)->toEqualWithDelta(32.7157, 0.0001)
        ->and((float) $model->longitude)->toEqualWithDelta(-117.1611, 0.0001)
        ->and($model->accuracy_meters)->toBe(12.5)
        ->and($model->within_geofence)->toBeTrue()
        ->and($model->recorded_at->equalTo($recordedAt))->toBeTrue();
});

it('records multiple pings for the same session', function () {
    $repository = new EloquentGpsPingRepository;

    $repository->record(new GpsPingRecord('ping-2', 'session-2', new GeoPoint(0, 0), 10.0, false, new DateTimeImmutable));
    $repository->record(new GpsPingRecord('ping-3', 'session-2', new GeoPoint(0, 0), 8.0, true, new DateTimeImmutable));

    expect(GpsPingModel::query()->where('presence_session_id', 'session-2')->count())->toBe(2);
});

it('returns the best accuracy among within-geofence pings only', function () {
    $repository = new EloquentGpsPingRepository;

    $repository->record(new GpsPingRecord('ping-4', 'session-3', new GeoPoint(0, 0), 5.0, false, new DateTimeImmutable));
    $repository->record(new GpsPingRecord('ping-5', 'session-3', new GeoPoint(0, 0), 40.0, true, new DateTimeImmutable));
    $repository->record(new GpsPingRecord('ping-6', 'session-3', new GeoPoint(0, 0), 15.0, true, new DateTimeImmutable));

    // The excellent (5.0) reading is ignored because it wasn't within the geofence.
    expect($repository->bestAccuracyWithinGeofence('session-3'))->toBe(15.0);
});

it('returns null when no within-geofence ping exists for the session', function () {
    $repository = new EloquentGpsPingRepository;

    $repository->record(new GpsPingRecord('ping-7', 'session-4', new GeoPoint(0, 0), 5.0, false, new DateTimeImmutable));

    expect($repository->bestAccuracyWithinGeofence('session-4'))->toBeNull();
});

it('returns null when the session has no pings at all', function () {
    expect((new EloquentGpsPingRepository)->bestAccuracyWithinGeofence('missing-session'))->toBeNull();
});

it('returns the most recent within-geofence ping timestamp', function () {
    $repository = new EloquentGpsPingRepository;
    $earlier = new DateTimeImmutable('2026-08-10 10:00:00');
    $later = new DateTimeImmutable('2026-08-10 10:05:00');

    $repository->record(new GpsPingRecord('ping-8', 'session-5', new GeoPoint(0, 0), 15.0, true, $earlier));
    $repository->record(new GpsPingRecord('ping-9', 'session-5', new GeoPoint(0, 0), 15.0, true, $later));
    $repository->record(new GpsPingRecord('ping-10', 'session-5', new GeoPoint(0, 0), 5.0, false, new DateTimeImmutable('2026-08-10 10:10:00')));

    expect($repository->latestWithinGeofencePingAt('session-5'))->toEqual($later);
});

it('returns null for latestWithinGeofencePingAt when no within-geofence ping exists', function () {
    $repository = new EloquentGpsPingRepository;

    $repository->record(new GpsPingRecord('ping-11', 'session-6', new GeoPoint(0, 0), 5.0, false, new DateTimeImmutable));

    expect($repository->latestWithinGeofencePingAt('session-6'))->toBeNull();
});
