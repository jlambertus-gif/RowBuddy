<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\ConfidenceScoreModel;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentConfidenceScoreRepository;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceScoreRecord;
use RowBuddy\QueuePresence\ValueObjects\ConfidenceTier;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('presence_confidence_scores', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('presence_session_id');
        $table->unsignedInteger('points');
        $table->string('tier');
        $table->timestamp('computed_at', 6);
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('presence_confidence_scores');
});

it('records a confidence score', function () {
    $repository = new EloquentConfidenceScoreRepository;
    $computedAt = new DateTimeImmutable('2026-08-24 10:00:00');

    $repository->record(new ConfidenceScoreRecord('score-1', 'session-1', 60, ConfidenceTier::LocationVerified, $computedAt));

    $latest = $repository->latestFor('session-1');

    expect($latest)->not->toBeNull()
        ->and($latest->id)->toBe('score-1')
        ->and($latest->points)->toBe(60)
        ->and($latest->tier)->toBe(ConfidenceTier::LocationVerified)
        ->and($latest->computedAt->format(DATE_ATOM))->toBe($computedAt->format(DATE_ATOM));
});

it('never updates an existing row: recomputing appends a new one', function () {
    $repository = new EloquentConfidenceScoreRepository;

    $repository->record(new ConfidenceScoreRecord('score-2', 'session-2', 40, ConfidenceTier::LocationVerified, new DateTimeImmutable('2026-08-24 10:00:00')));
    $repository->record(new ConfidenceScoreRecord('score-3', 'session-2', 140, ConfidenceTier::EvidenceVerified, new DateTimeImmutable('2026-08-24 10:05:00')));

    expect(ConfidenceScoreModel::query()->where('presence_session_id', 'session-2')->count())->toBe(2);
});

it('returns the most recently computed score for a session', function () {
    $repository = new EloquentConfidenceScoreRepository;

    $repository->record(new ConfidenceScoreRecord('score-4', 'session-3', 40, ConfidenceTier::LocationVerified, new DateTimeImmutable('2026-08-24 10:00:00')));
    $repository->record(new ConfidenceScoreRecord('score-5', 'session-3', 140, ConfidenceTier::EvidenceVerified, new DateTimeImmutable('2026-08-24 10:05:00')));

    expect($repository->latestFor('session-3')->id)->toBe('score-5')
        ->and($repository->latestFor('session-3')->tier)->toBe(ConfidenceTier::EvidenceVerified);
});

it('returns null when the session has no score recorded yet', function () {
    expect((new EloquentConfidenceScoreRepository)->latestFor('missing-session'))->toBeNull();
});
