<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * Deliberately limited to what Phase 4 actually authorizes (ADR-015 §4/§5)
 * — Captured, Held, ReleasedToSeller, and RefundedToBuyer do not exist
 * here yet. They belong to Phase 5's capture trigger and are documented
 * only in ADR-004/ADR-015, not modeled in code, until that contract exists.
 */
enum PaymentIntentStatus: string
{
    case Authorized = 'authorized';
    case Failed = 'failed';
}
