<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Defense-in-depth translation of the `ratings.(transfer_id, rater_id)`
 * unique constraint (ADR-024 §3/Consequences) — mirrors
 * `DisputeAlreadyExistsForTransfer`'s identical shape and purpose,
 * adapted to Ratings' two independent rating slots per transfer.
 */
final class RatingAlreadyExistsForTransferAndRater extends DomainException
{
    public static function forTransferAndRater(string $transferId, string $raterId): self
    {
        return new self(
            "A rating already exists for transfer [{$transferId}] from rater [{$raterId}]."
        );
    }
}
