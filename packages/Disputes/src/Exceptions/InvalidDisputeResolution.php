<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a resolution's outcome and refund amount are inconsistent
 * (ADR-021 §6, ADR-022 §2) — `ReleaseToSeller`/`Cancelled` must carry no
 * amount, `RefundToBuyer`/`Split` must carry a positive one. This
 * aggregate enforces only that internal consistency; the
 * amount-cannot-exceed-the-captured-total invariant (ADR-022 §2) belongs
 * to `PaymentIntent::refund()`, not to `Dispute`, since `Dispute` never
 * knows the captured total.
 */
final class InvalidDisputeResolution extends DomainException
{
    public static function amountRequiredForOutcome(string $disputeId, string $outcome): self
    {
        return new self(
            "Dispute [{$disputeId}] resolution [{$outcome}] requires a positive refund amount."
        );
    }

    public static function amountNotAllowedForOutcome(string $disputeId, string $outcome): self
    {
        return new self(
            "Dispute [{$disputeId}] resolution [{$outcome}] must not carry a refund amount."
        );
    }
}
