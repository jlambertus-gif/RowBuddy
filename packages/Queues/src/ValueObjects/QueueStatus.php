<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

/**
 * The queue authorship lifecycle from ADR-005
 * (docs/decisions/005-hybrid-queue-authorship.md): a flat "published"
 * boolean cannot express the human review gate between "a queue exists"
 * and "money can be put at risk against it".
 */
enum QueueStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
}
