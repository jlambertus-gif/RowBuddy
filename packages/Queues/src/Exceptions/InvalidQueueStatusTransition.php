<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Exceptions;

use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\Exceptions\DomainException;

final class InvalidQueueStatusTransition extends DomainException
{
    public static function from(QueueStatus $current, QueueStatus $attempted): self
    {
        return new self(
            "Cannot transition queue from [{$current->value}] to [{$attempted->value}]."
        );
    }
}
