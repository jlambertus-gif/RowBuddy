<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\QueuePresence\Exceptions\DuplicateActivePresenceSession;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EloquentPresenceSessionRepository;
use RowBuddy\QueuePresence\PresenceSession;
use RowBuddy\QueuePresence\ValueObjects\PresenceSessionStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('presence_sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('queue_id');
        $table->unsignedBigInteger('seller_id');
        $table->timestamp('started_at');
        $table->timestamp('ended_at')->nullable();
        $table->string('status');
        $table->timestamps();
    });

    Capsule::connection()->statement(
        'CREATE UNIQUE INDEX presence_sessions_one_active_per_seller_per_queue '
        .'ON presence_sessions (seller_id, queue_id) '
        ."WHERE status = 'active'"
    );
});

afterEach(function () {
    Capsule::schema()->dropIfExists('presence_sessions');
});

it('round-trips an active presence session through the repository', function () {
    $repository = new EloquentPresenceSessionRepository;
    $startedAt = new DateTimeImmutable('2026-08-01 10:00:00');

    $session = PresenceSession::start('session-1', 'queue-1', '101', new FrozenClock($startedAt));

    $repository->save($session);
    $found = $repository->findById('session-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('session-1')
        ->and($found->queueId)->toBe('queue-1')
        ->and($found->sellerId)->toBe('101')
        ->and($found->startedAt)->toEqual($startedAt)
        ->and($found->status())->toBe(PresenceSessionStatus::Active)
        ->and($found->endedAt())->toBeNull()
        ->and($found->releaseEvents())->toBe([]);
});

it('round-trips ended_at once a session has ended', function () {
    $repository = new EloquentPresenceSessionRepository;
    $endedAt = new DateTimeImmutable('2026-08-01 10:05:00');

    $session = PresenceSession::start('session-11', 'queue-1', '101', new FrozenClock);
    $session->end(new FrozenClock($endedAt));
    $repository->save($session);

    expect($repository->findById('session-11')->endedAt())->toEqual($endedAt);
});

it('returns null when the session does not exist', function () {
    expect((new EloquentPresenceSessionRepository)->findById('missing'))->toBeNull();
});

it('persists a status transition made after reloading from the repository', function () {
    $repository = new EloquentPresenceSessionRepository;

    $session = PresenceSession::start('session-2', 'queue-1', '101', new FrozenClock);
    $repository->save($session);

    $reloaded = $repository->findById('session-2');
    $reloaded->end(new FrozenClock);
    $repository->save($reloaded);

    expect($repository->findById('session-2')->status())->toBe(PresenceSessionStatus::Ended);
});

it('rejects a second active session for the same seller and queue', function () {
    $repository = new EloquentPresenceSessionRepository;

    $repository->save(PresenceSession::start('session-3', 'queue-1', '101', new FrozenClock));

    $repository->save(PresenceSession::start('session-4', 'queue-1', '101', new FrozenClock));
})->throws(DuplicateActivePresenceSession::class);

it('allows a new active session for the same seller and queue once the previous one has ended', function () {
    $repository = new EloquentPresenceSessionRepository;

    $first = PresenceSession::start('session-5', 'queue-1', '101', new FrozenClock);
    $repository->save($first);
    $first->end(new FrozenClock);
    $repository->save($first);

    $repository->save(PresenceSession::start('session-6', 'queue-1', '101', new FrozenClock));

    expect($repository->findById('session-6')->status())->toBe(PresenceSessionStatus::Active);
});

it('allows two different sellers to each have an active session at the same queue', function () {
    $repository = new EloquentPresenceSessionRepository;

    $repository->save(PresenceSession::start('session-7', 'queue-1', '101', new FrozenClock));
    $repository->save(PresenceSession::start('session-8', 'queue-1', '102', new FrozenClock));

    expect($repository->findById('session-7')->status())->toBe(PresenceSessionStatus::Active)
        ->and($repository->findById('session-8')->status())->toBe(PresenceSessionStatus::Active);
});

it('allows the same seller to have active sessions at two different queues', function () {
    $repository = new EloquentPresenceSessionRepository;

    $repository->save(PresenceSession::start('session-9', 'queue-1', '101', new FrozenClock));
    $repository->save(PresenceSession::start('session-10', 'queue-2', '101', new FrozenClock));

    expect($repository->findById('session-9')->status())->toBe(PresenceSessionStatus::Active)
        ->and($repository->findById('session-10')->status())->toBe(PresenceSessionStatus::Active);
});
