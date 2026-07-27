<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class DuplicateActivePresenceSession extends DomainException
{
    public static function forSellerAndQueue(string $sellerId, string $queueId): self
    {
        return new self(
            "Seller [{$sellerId}] already has an active presence session for queue [{$queueId}]."
        );
    }
}
