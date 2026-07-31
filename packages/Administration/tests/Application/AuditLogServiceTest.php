<?php

declare(strict_types=1);

use RowBuddy\Administration\Application\AuditLogService;
use RowBuddy\Administration\Support\AuditEventDisplayRegistry;
use RowBuddy\Administration\Tests\Fakes\InMemoryAuditEventLookup;
use RowBuddy\Administration\ValueObjects\AuditEventSnapshot;

it('presents a registered event with only its allowed fields', function () {
    $events = new InMemoryAuditEventLookup;
    $events->events['event-1'] = new AuditEventSnapshot(
        id: 'event-1',
        eventName: 'queues.queue_approved',
        subjectType: 'queue',
        subjectId: 'queue-1',
        payload: ['queue_id' => 'queue-1', 'approved_by_user_id' => '999'],
        occurredAt: new DateTimeImmutable('2026-08-01 00:00:00'),
    );
    $service = new AuditLogService($events, new AuditEventDisplayRegistry);

    $presented = $service->findById('event-1');

    expect($presented->fields)->toBe(['queue_id' => 'queue-1', 'approved_by_user_id' => '999'])
        ->and($presented->eventName)->toBe('queues.queue_approved')
        ->and($presented->subjectType)->toBe('queue')
        ->and($presented->subjectId)->toBe('queue-1');
});

it('returns null for an unregistered event type via findById — it must not appear at all, not even its metadata', function () {
    $events = new InMemoryAuditEventLookup;
    $events->events['event-1'] = new AuditEventSnapshot(
        id: 'event-1',
        eventName: 'some_future_module.some_new_event',
        subjectType: 'something',
        subjectId: 'id-1',
        payload: ['secret' => 'value'],
        occurredAt: new DateTimeImmutable('2026-08-01 00:00:00'),
    );
    $service = new AuditLogService($events, new AuditEventDisplayRegistry);

    expect($service->findById('event-1'))->toBeNull();
});

it('returns null when the event does not exist', function () {
    $service = new AuditLogService(new InMemoryAuditEventLookup, new AuditEventDisplayRegistry);

    expect($service->findById('missing'))->toBeNull();
});

it('lists recent events, most recently occurred first, each presented through the registry', function () {
    $events = new InMemoryAuditEventLookup;
    $events->events['event-1'] = new AuditEventSnapshot('event-1', 'queues.queue_published', 'queue', 'queue-1', ['queue_id' => 'queue-1'], new DateTimeImmutable('2026-08-01 00:00:00'));
    $events->events['event-2'] = new AuditEventSnapshot('event-2', 'queues.queue_approved', 'queue', 'queue-1', ['queue_id' => 'queue-1', 'approved_by_user_id' => '999'], new DateTimeImmutable('2026-08-02 00:00:00'));
    $service = new AuditLogService($events, new AuditEventDisplayRegistry);

    $recent = $service->listRecent(10);

    expect($recent)->toHaveCount(2)
        ->and($recent[0]->id)->toBe('event-2')
        ->and($recent[1]->id)->toBe('event-1');
});

it('excludes an unregistered event from listRecent entirely, leaving only the registered ones', function () {
    $events = new InMemoryAuditEventLookup;
    $events->events['event-1'] = new AuditEventSnapshot('event-1', 'queues.queue_published', 'queue', 'queue-1', ['queue_id' => 'queue-1'], new DateTimeImmutable('2026-08-01 00:00:00'));
    $events->events['event-2'] = new AuditEventSnapshot('event-2', 'some_future_module.some_new_event', 'something', 'id-1', ['secret' => 'value'], new DateTimeImmutable('2026-08-02 00:00:00'));
    $service = new AuditLogService($events, new AuditEventDisplayRegistry);

    $recent = $service->listRecent(10);

    expect($recent)->toHaveCount(1)
        ->and($recent[0]->id)->toBe('event-1');
});

it('respects the limit passed to listRecent', function () {
    $events = new InMemoryAuditEventLookup;
    $events->events['event-1'] = new AuditEventSnapshot('event-1', 'queues.queue_published', 'queue', 'queue-1', [], new DateTimeImmutable('2026-08-01 00:00:00'));
    $events->events['event-2'] = new AuditEventSnapshot('event-2', 'queues.queue_approved', 'queue', 'queue-1', [], new DateTimeImmutable('2026-08-02 00:00:00'));
    $service = new AuditLogService($events, new AuditEventDisplayRegistry);

    expect($service->listRecent(1))->toHaveCount(1);
});
