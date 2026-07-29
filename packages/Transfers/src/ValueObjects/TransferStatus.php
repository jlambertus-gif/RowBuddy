<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\ValueObjects;

/**
 * `Confirmed`, `Expired`, and `Cancelled` are all terminal (ADR-017 §3)
 * — a `Transfer` has exactly one real decision point (did both parties
 * confirm before the window closed), unlike `Auction`'s multi-step
 * machine. `Expired` (the window simply ran out, ADR-018 §3) and
 * `Cancelled` (an explicit default or failed re-authorization,
 * ADR-018 §2/§4) are kept distinct even though both lead to the same
 * payment consequence (void the authorization, no capture) — they are
 * semantically different outcomes for reporting and audit purposes.
 */
enum TransferStatus: string
{
    case Issued = 'issued';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
