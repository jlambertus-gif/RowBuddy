<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\QueueModerationService;
use RowBuddy\Queues\Events\QueueApproved;
use RowBuddy\Queues\Events\QueuePublished;
use RowBuddy\Queues\Events\QueueRejected;
use RowBuddy\Queues\Exceptions\InvalidQueueStatusTransition;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\Queues\Gating\JurisdictionGate;
use RowBuddy\Queues\Gating\QueueGateChecker;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\Tests\Fakes\InMemoryJurisdictionRuleRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryQueueRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryRestrictedCategoryRepository;
use RowBuddy\Queues\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Queues\ValueObjects\JurisdictionRule;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Like QueueSubmissionServiceTest, this suite runs without Laravel and
 * without a database: every collaborator is a plain in-memory fake
 * implementing the same domain-facing interface the real Eloquent
 * adapters implement.
 */
function makeModerationService(
    InMemoryQueueRepository $queues,
    InMemoryRestrictedCategoryRepository $restrictedCategories,
    InMemoryJurisdictionRuleRepository $jurisdictionRules,
    RecordingDomainEventPublisher $events,
): QueueModerationService {
    return new QueueModerationService(
        $queues,
        new QueueGateChecker($restrictedCategories, $jurisdictionRules, new JurisdictionGate),
        $events,
        new FrozenClock(new DateTimeImmutable('2026-06-01')),
    );
}

function aModerationTestGeofence(): Geofence
{
    return new Geofence(new GeoPoint(32.7157, -117.1611), 200);
}

function aPendingQueue(string $id = 'queue-1'): Queue
{
    $queue = Queue::submitForApproval($id, 'concert', 'US', aModerationTestGeofence(), 'user-9', new FrozenClock);
    $queue->releaseEvents(); // drain the submission event: this helper represents a queue already sitting in storage

    return $queue;
}

it('lists only pending queues', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(aPendingQueue('queue-1'));
    $queues->save(Queue::publishDirectly('queue-2', 'concert', 'US', aModerationTestGeofence(), 'venue-1'));

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, new InMemoryJurisdictionRuleRepository, new RecordingDomainEventPublisher);

    $pending = $service->listPending();

    expect($pending)->toHaveCount(1)
        ->and($pending[0]->id)->toBe('queue-1');
});

it('approves a pending queue, persists it, and publishes the domain event', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(aPendingQueue());
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));
    $events = new RecordingDomainEventPublisher;

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, $jurisdictionRules, $events);

    $queue = $service->approve('queue-1', 'admin-1');

    expect($queue->status())->toBe(QueueStatus::Approved)
        ->and($queues->findById('queue-1')->status())->toBe(QueueStatus::Approved)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(QueueApproved::class);
});

it('refuses to approve a queue that has just become blocked by the jurisdiction gate', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(aPendingQueue());
    $restrictedCategories = new InMemoryRestrictedCategoryRepository;
    $restrictedCategories->restrict('concert', 'US');
    $events = new RecordingDomainEventPublisher;

    $service = makeModerationService($queues, $restrictedCategories, new InMemoryJurisdictionRuleRepository, $events);

    expect(fn () => $service->approve('queue-1', 'admin-1'))->toThrow(QueueSubmissionBlocked::class);

    expect($queues->findById('queue-1')->status())->toBe(QueueStatus::Pending)
        ->and($events->published)->toBe([]);
});

it('cannot approve a queue that is not pending', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(Queue::publishDirectly('queue-1', 'concert', 'US', aModerationTestGeofence(), 'venue-1'));
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, $jurisdictionRules, new RecordingDomainEventPublisher);

    expect(fn () => $service->approve('queue-1', 'admin-1'))
        ->toThrow(InvalidQueueStatusTransition::class);
});

it('rejects a pending queue with a reason, persists it, and publishes the domain event', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(aPendingQueue());
    $events = new RecordingDomainEventPublisher;

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, new InMemoryJurisdictionRuleRepository, $events);

    $queue = $service->reject('queue-1', 'admin-1', 'Restricted category.');

    expect($queue->status())->toBe(QueueStatus::Rejected)
        ->and($queues->findById('queue-1')->status())->toBe(QueueStatus::Rejected)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(QueueRejected::class);
});

it('cannot reject a queue that is not pending', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(Queue::publishDirectly('queue-1', 'concert', 'US', aModerationTestGeofence(), 'venue-1'));

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, new InMemoryJurisdictionRuleRepository, new RecordingDomainEventPublisher);

    expect(fn () => $service->reject('queue-1', 'admin-1', 'too late'))
        ->toThrow(InvalidQueueStatusTransition::class);
});

it('publishes an approved queue, persists it, and publishes the domain event', function () {
    $queue = aPendingQueue();
    $queue->approve('admin-1', new FrozenClock);
    $queue->releaseEvents();

    $queues = new InMemoryQueueRepository;
    $queues->save($queue);
    $events = new RecordingDomainEventPublisher;

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, new InMemoryJurisdictionRuleRepository, $events);

    $published = $service->publish('queue-1');

    expect($published->status())->toBe(QueueStatus::Published)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(QueuePublished::class);
});

it('cannot publish a queue that is not approved', function () {
    $queues = new InMemoryQueueRepository;
    $queues->save(aPendingQueue());

    $service = makeModerationService($queues, new InMemoryRestrictedCategoryRepository, new InMemoryJurisdictionRuleRepository, new RecordingDomainEventPublisher);

    expect(fn () => $service->publish('queue-1'))
        ->toThrow(InvalidQueueStatusTransition::class);
});

it('throws NotFoundException for an unknown queue id on every action', function () {
    $service = makeModerationService(new InMemoryQueueRepository, new InMemoryRestrictedCategoryRepository, new InMemoryJurisdictionRuleRepository, new RecordingDomainEventPublisher);

    expect(fn () => $service->approve('missing', 'admin-1'))->toThrow(NotFoundException::class);
    expect(fn () => $service->reject('missing', 'admin-1', 'reason'))->toThrow(NotFoundException::class);
    expect(fn () => $service->publish('missing'))->toThrow(NotFoundException::class);
});
