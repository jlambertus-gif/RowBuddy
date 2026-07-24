<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\Discovery\CoverageAreaAssigner;
use RowBuddy\Queues\Application\QueueSubmissionService;
use RowBuddy\Queues\Events\QueueSubmittedForApproval;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\Queues\Gating\JurisdictionGate;
use RowBuddy\Queues\Gating\QueueGateChecker;
use RowBuddy\Queues\Tests\Fakes\InMemoryJurisdictionRuleRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryQueueDiscoveryRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryQueueRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryRestrictedCategoryRepository;
use RowBuddy\Queues\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Queues\ValueObjects\JurisdictionRule;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * This entire suite runs without Laravel, without a database, and without
 * Eloquent: every collaborator is a plain in-memory fake implementing the
 * same domain-facing interface the real Eloquent adapters implement. This
 * is what proves the business decisions in QueueSubmissionService are
 * genuinely framework-independent, not merely "tested with sqlite".
 */
function makeQueueSubmissionService(
    InMemoryQueueRepository $queues,
    InMemoryRestrictedCategoryRepository $restrictedCategories,
    InMemoryJurisdictionRuleRepository $jurisdictionRules,
    RecordingDomainEventPublisher $events,
    ?InMemoryQueueDiscoveryRepository $discovery = null,
): QueueSubmissionService {
    return new QueueSubmissionService(
        $queues,
        new QueueGateChecker($restrictedCategories, $jurisdictionRules, new JurisdictionGate),
        $events,
        new FrozenClock(new DateTimeImmutable('2026-06-01')),
        new CoverageAreaAssigner($discovery ?? new InMemoryQueueDiscoveryRepository),
    );
}

function aTestGeofence(): Geofence
{
    return new Geofence(new GeoPoint(32.7157, -117.1611), 200);
}

it('submits a queue for approval, persists it, and publishes its domain event', function () {
    $queues = new InMemoryQueueRepository;
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));
    $events = new RecordingDomainEventPublisher;

    $service = makeQueueSubmissionService($queues, new InMemoryRestrictedCategoryRepository, $jurisdictionRules, $events);

    $queue = $service->submitForApproval('queue-1', 'concert', 'US', aTestGeofence(), 'user-9');

    expect($queue->status())->toBe(QueueStatus::Pending)
        ->and($queues->findById('queue-1'))->toBe($queue)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(QueueSubmittedForApproval::class);
});

it('blocks submission of a globally restricted category before ever persisting it', function () {
    $queues = new InMemoryQueueRepository;
    $restrictedCategories = new InMemoryRestrictedCategoryRepository;
    $restrictedCategories->restrict('medical_emergency', null);
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));
    $events = new RecordingDomainEventPublisher;

    $service = makeQueueSubmissionService($queues, $restrictedCategories, $jurisdictionRules, $events);

    expect(fn () => $service->submitForApproval('queue-2', 'medical_emergency', 'US', aTestGeofence(), 'user-9'))
        ->toThrow(QueueSubmissionBlocked::class);

    expect($queues->findById('queue-2'))->toBeNull()
        ->and($events->published)->toBe([]);
});

it('blocks submission when the jurisdiction has no rule permitting that category at all', function () {
    $service = makeQueueSubmissionService(
        $queues = new InMemoryQueueRepository,
        new InMemoryRestrictedCategoryRepository,
        new InMemoryJurisdictionRuleRepository, // no rules at all: fail closed
        new RecordingDomainEventPublisher,
    );

    expect(fn () => $service->submitForApproval('queue-3', 'concert', 'FR', aTestGeofence(), 'user-9'))
        ->toThrow(QueueSubmissionBlocked::class);

    expect($queues->findById('queue-3'))->toBeNull();
});

it('publishes an admin-curated queue directly when not gated', function () {
    $queues = new InMemoryQueueRepository;
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));
    $events = new RecordingDomainEventPublisher;

    $service = makeQueueSubmissionService($queues, new InMemoryRestrictedCategoryRepository, $jurisdictionRules, $events);

    $queue = $service->publishDirectly('queue-4', 'concert', 'US', aTestGeofence(), 'venue-42');

    expect($queue->status())->toBe(QueueStatus::Published)
        ->and($queues->findById('queue-4'))->toBe($queue)
        ->and($events->published)->toBe([]);
});

it('blocks a direct-publish attempt for a restricted category, even for admin-curated queues', function () {
    $restrictedCategories = new InMemoryRestrictedCategoryRepository;
    $restrictedCategories->restrict('school_admissions', null);
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));

    $service = makeQueueSubmissionService(
        $queues = new InMemoryQueueRepository,
        $restrictedCategories,
        $jurisdictionRules,
        new RecordingDomainEventPublisher,
    );

    expect(fn () => $service->publishDirectly('queue-5', 'school_admissions', 'US', aTestGeofence(), 'venue-1'))
        ->toThrow(QueueSubmissionBlocked::class);

    expect($queues->findById('queue-5'))->toBeNull();
});

it('assigns a default coverage area automatically when publishing directly', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));

    $service = makeQueueSubmissionService(
        new InMemoryQueueRepository,
        new InMemoryRestrictedCategoryRepository,
        $jurisdictionRules,
        new RecordingDomainEventPublisher,
        $discovery,
    );

    $queue = $service->publishDirectly('queue-7', 'concert', 'US', aTestGeofence(), 'venue-42');

    expect($discovery->assignedCoverageAreas)->toHaveKey('queue-7')
        ->and($discovery->assignedCoverageAreas['queue-7']->polygons)->not->toBe([]);
});

it('does not assign a coverage area when a queue is only submitted for approval, not published', function () {
    $discovery = new InMemoryQueueDiscoveryRepository;
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));

    $service = makeQueueSubmissionService(
        new InMemoryQueueRepository,
        new InMemoryRestrictedCategoryRepository,
        $jurisdictionRules,
        new RecordingDomainEventPublisher,
        $discovery,
    );

    $service->submitForApproval('queue-8', 'concert', 'US', aTestGeofence(), 'user-9');

    expect($discovery->assignedCoverageAreas)->toBe([]);
});

it('prefers a category-specific jurisdiction rule over a permissive country-wide one', function () {
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));
    $jurisdictionRules->addRule(new JurisdictionRule('US', 'concert', false, new DateTimeImmutable('2020-01-01'), null));

    $service = makeQueueSubmissionService(
        $queues = new InMemoryQueueRepository,
        new InMemoryRestrictedCategoryRepository,
        $jurisdictionRules,
        new RecordingDomainEventPublisher,
    );

    expect(fn () => $service->submitForApproval('queue-6', 'concert', 'US', aTestGeofence(), 'user-9'))
        ->toThrow(QueueSubmissionBlocked::class);
});
