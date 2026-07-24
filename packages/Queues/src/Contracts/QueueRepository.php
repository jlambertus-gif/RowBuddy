<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueStatus;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept — implementations (e.g. an Eloquent adapter) translate between
 * whatever storage technology backs them and the {@see Queue} aggregate,
 * never the reverse.
 */
interface QueueRepository
{
    public function save(Queue $queue): void;

    public function findById(string $id): ?Queue;

    /**
     * @return list<Queue>
     */
    public function findByStatus(QueueStatus $status): array;
}
