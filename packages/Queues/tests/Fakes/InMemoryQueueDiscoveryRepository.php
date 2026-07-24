<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Tests\Fakes;

use RowBuddy\Queues\Contracts\QueueDiscoveryRepository;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

final class InMemoryQueueDiscoveryRepository implements QueueDiscoveryRepository
{
    /** @var array<string, CoverageArea> */
    public array $assignedCoverageAreas = [];

    /** @var list<Queue> */
    private array $queuesToReturn = [];

    /**
     * @param  list<Queue>  $queues
     */
    public function willReturn(array $queues): void
    {
        $this->queuesToReturn = $queues;
    }

    public function defineCoverageArea(string $queueId, CoverageArea $coverageArea): void
    {
        $this->assignedCoverageAreas[$queueId] = $coverageArea;
    }

    public function discoverByLocation(GeoPoint $point): array
    {
        return $this->queuesToReturn;
    }
}
