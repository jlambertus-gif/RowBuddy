<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class InvalidEvidencePhoto extends DomainException
{
    public static function notADecodableImage(): self
    {
        return new self('The uploaded evidence photo could not be decoded as an image.');
    }
}
