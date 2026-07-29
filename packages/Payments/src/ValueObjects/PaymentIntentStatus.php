<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * `Captured`, `CaptureFailed`, and `Cancelled` were added in Phase 5 per
 * ADR-019 §2 — `Held`, `ReleasedToSeller`, and `RefundedToBuyer` still do
 * not exist: seller payout execution stays out of scope (ADR-019 §5).
 * `CaptureFailed` (confirmation happened, but the real Stripe capture
 * call itself failed) is deliberately distinct from `Cancelled` (the
 * authorization was voided without ever attempting a capture — a
 * transfer window expired unconfirmed, or a default was recorded).
 */
enum PaymentIntentStatus: string
{
    case Authorized = 'authorized';
    case Failed = 'failed';
    case Captured = 'captured';
    case CaptureFailed = 'capture_failed';
    case Cancelled = 'cancelled';
}
