<?php

declare(strict_types=1);

use RowBuddy\Queues\Application\Discovery\CoverageAreaAssigner;
use RowBuddy\Queues\Application\Discovery\QueueDiscoveryService;
use RowBuddy\Queues\Application\QueueModerationService;
use RowBuddy\Queues\Application\QueueSubmissionService;
use RowBuddy\Queues\Gating\JurisdictionGate;
use RowBuddy\Queues\Gating\QueueGateChecker;
use RowBuddy\Queues\Infrastructure\Eloquent\EloquentQueueRepository;
use RowBuddy\Queues\Infrastructure\PostGIS\PostGISQueueDiscoveryRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryJurisdictionRuleRepository;
use RowBuddy\Queues\Tests\Fakes\InMemoryRestrictedCategoryRepository;
use RowBuddy\Queues\Tests\Fakes\RecordingDomainEventPublisher;

use function RowBuddy\Queues\Tests\Support\bootPostgisTestConnection;
use function RowBuddy\Queues\Tests\Support\rollbackPostgisTestConnection;

use RowBuddy\Queues\ValueObjects\JurisdictionRule;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * True end-to-end proof against real PostgreSQL/PostGIS (ADR-007 §8):
 * publishing a queue — through the real application services, not
 * fakes — automatically makes it discoverable, with no manual step in
 * between. This is what directly de-risks the gap Sprint 6a flagged
 * ("nothing yet calls defineCoverageArea in production").
 */
beforeEach(fn () => bootPostgisTestConnection($this));

afterEach(fn () => rollbackPostgisTestConnection());

function realGateChecker(): QueueGateChecker
{
    $jurisdictionRules = new InMemoryJurisdictionRuleRepository;
    $jurisdictionRules->addRule(new JurisdictionRule('US', null, true, new DateTimeImmutable('2020-01-01'), null));

    return new QueueGateChecker(new InMemoryRestrictedCategoryRepository, $jurisdictionRules, new JurisdictionGate);
}

it('is discoverable immediately after being published directly by an admin', function () {
    $queues = new EloquentQueueRepository;
    $discovery = new PostGISQueueDiscoveryRepository;
    $clock = new FrozenClock(new DateTimeImmutable('2026-06-01'));

    $submissionService = new QueueSubmissionService(
        $queues,
        realGateChecker(),
        new RecordingDomainEventPublisher,
        $clock,
        new CoverageAreaAssigner($discovery),
    );

    $center = new GeoPoint(32.7157, -117.1611);
    $submissionService->publishDirectly('e0000000-0000-0000-0000-000000000001', 'concert', 'US', new Geofence($center, 300), 'venue-1');

    $discoveryService = new QueueDiscoveryService($discovery);
    $page = $discoveryService->discover($center, 1, 20);

    expect($page->total)->toBe(1)
        ->and($page->items[0]->queue->id)->toBe('e0000000-0000-0000-0000-000000000001');
});

it('is discoverable immediately after an admin approves and publishes a user-submitted queue', function () {
    $queues = new EloquentQueueRepository;
    $discovery = new PostGISQueueDiscoveryRepository;
    $clock = new FrozenClock(new DateTimeImmutable('2026-06-01'));
    $events = new RecordingDomainEventPublisher;

    $submissionService = new QueueSubmissionService($queues, realGateChecker(), $events, $clock, new CoverageAreaAssigner($discovery));
    $moderationService = new QueueModerationService($queues, realGateChecker(), $events, $clock, new CoverageAreaAssigner($discovery));

    $center = new GeoPoint(40.7128, -74.0060);
    $submissionService->submitForApproval('e0000000-0000-0000-0000-000000000002', 'concert', 'US', new Geofence($center, 250), 'user-9');

    $discoveryService = new QueueDiscoveryService($discovery);

    // Not discoverable while still pending.
    expect($discoveryService->discover($center, 1, 20)->total)->toBe(0);

    $moderationService->approve('e0000000-0000-0000-0000-000000000002', 'admin-1');

    // Still not discoverable: approved, not yet published.
    expect($discoveryService->discover($center, 1, 20)->total)->toBe(0);

    $moderationService->publish('e0000000-0000-0000-0000-000000000002');

    $page = $discoveryService->discover($center, 1, 20);
    expect($page->total)->toBe(1)
        ->and($page->items[0]->queue->id)->toBe('e0000000-0000-0000-0000-000000000002');
});

it('is not discoverable from a point outside the automatically derived coverage area', function () {
    $queues = new EloquentQueueRepository;
    $discovery = new PostGISQueueDiscoveryRepository;
    $clock = new FrozenClock(new DateTimeImmutable('2026-06-01'));

    $submissionService = new QueueSubmissionService(
        $queues,
        realGateChecker(),
        new RecordingDomainEventPublisher,
        $clock,
        new CoverageAreaAssigner($discovery),
    );

    $center = new GeoPoint(32.7157, -117.1611);
    $submissionService->publishDirectly('e0000000-0000-0000-0000-000000000003', 'concert', 'US', new Geofence($center, 50), 'venue-1');

    $farAway = new GeoPoint(34.0522, -118.2437);
    $discoveryService = new QueueDiscoveryService($discovery);

    expect($discoveryService->discover($farAway, 1, 20)->total)->toBe(0);
});
