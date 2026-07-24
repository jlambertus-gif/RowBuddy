<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Tests\Fakes;

use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Queue;

final class InMemoryQueueRepository implements QueueRepository
{
    /** @var array<string, Queue> */
    public array $saved = [];

    public function save(Queue $queue): void
    {
        $this->saved[$queue->id] = $queue;
    }

    public function findById(string $id): ?Queue
    {
        return $this->saved[$id] ?? null;
    }
}
