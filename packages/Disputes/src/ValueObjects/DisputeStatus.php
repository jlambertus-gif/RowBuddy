<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\ValueObjects;

/**
 * `Opened` covers both the buyer's initial filing and the seller's
 * response window (ADR-021 §1/§4) — nothing this codebase's frozen Phase
 * 6 decisions define distinguishes "just filed" from "under review" as a
 * separately-guarded state: the response deadline is a lazily-checked
 * fact (ADR-021 §4), never a stored transition, so a second status for
 * it would carry no behavior. `Resolved` is terminal (ADR-021 §5) —
 * "Resolved" and "Closed" are the same concept in this codebase; no
 * reopening, no appeal, no second review state exists.
 */
enum DisputeStatus: string
{
    case Opened = 'opened';
    case Resolved = 'resolved';
}
