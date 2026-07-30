<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * `Captured`, `CaptureFailed`, and `Cancelled` were added in Phase 5 per
 * ADR-019 §2 — `Held` and `ReleasedToSeller` still do not exist: seller
 * payout execution stays out of scope (ADR-019 §5). `CaptureFailed`
 * (confirmation happened, but the real Stripe capture call itself
 * failed) is deliberately distinct from `Cancelled` (the authorization
 * was voided without ever attempting a capture — a transfer window
 * expired unconfirmed, or a default was recorded).
 *
 * `Refunded` was added in Phase 6 per ADR-022 §3 — deliberately a single
 * state regardless of whether the refunded amount equals the full
 * captured total or a lesser amount: "split" is a `Dispute`-resolution-
 * outcome concept (`packages/Disputes`), never a distinct `PaymentIntent`
 * lifecycle state.
 */
enum PaymentIntentStatus: string
{
    case Authorized = 'authorized';
    case Failed = 'failed';
    case Captured = 'captured';
    case CaptureFailed = 'capture_failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
