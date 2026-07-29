<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\ValueObjects;

/**
 * The four resolution outcomes ADR-021 §6 and ADR-022 establish. `Split`
 * is kept as its own outcome, distinct from `RefundToBuyer`, purely for
 * audit/reporting clarity — mechanically both simply carry a refund
 * amount (ADR-022 §3: "split" is a `Dispute`-resolution-outcome concept,
 * never a distinct `PaymentIntent` state). This aggregate does not know
 * the captured total, so it cannot itself distinguish "the full amount"
 * from "a lesser amount" — that distinction is the caller's
 * responsibility when choosing which outcome to record.
 */
enum DisputeResolutionOutcome: string
{
    case ReleaseToSeller = 'release_to_seller';
    case RefundToBuyer = 'refund_to_buyer';
    case Split = 'split';
    case Cancelled = 'cancelled';
}
