<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class InvalidEvidencePhoto extends DomainException
{
    public static function notADecodableImage(): self
    {
        return new self('The submitted evidence photo could not be decoded as an image.');
    }
}
