<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application\Discovery;

use RowBuddy\Queues\Contracts\QueueDiscoveryRepository;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\Queues\ValueObjects\QueueStatus;

/**
 * Assigns a default, automatically derived coverage area whenever a
 * queue becomes published (ADR-007 §8) — approximating the queue's
 * existing `Geofence` as a polygon, transparently, with no separate
 * domain event: it's treated as part of the same publish operation that
 * already raises `QueuePublished`, not a distinct business event.
 *
 * Shared by both `QueueSubmissionService::publishDirectly()` and
 * `QueueModerationService::publish()` so this logic exists in exactly
 * one place, the same pattern as `QueueGateChecker` (Sprint 5) and
 * `QueueModelMapper` (Sprint 6a).
 */
final class CoverageAreaAssigner
{
    public function __construct(private readonly QueueDiscoveryRepository $discovery) {}

    public function assignDefaultCoverageArea(Queue $queue): void
    {
        if ($queue->status() !== QueueStatus::Published) {
            return;
        }

        $this->discovery->defineCoverageArea(
            $queue->id,
            CoverageArea::approximatingCircle($queue->geofence->center, $queue->geofence->radiusInMeters),
        );
    }
}
