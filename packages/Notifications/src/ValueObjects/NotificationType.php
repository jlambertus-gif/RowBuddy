<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\ValueObjects;

/**
 * The exhaustive, closed set of notification types approved for Phase 7
 * (ADR-025 §6) — exactly the eight (event, recipient) pairs in that
 * table. Adding a ninth requires a new, separately approved decision
 * (ADR-025 Consequences); this enum is deliberately closed, not a
 * config-driven open string, so that requirement is enforced by the type
 * system itself.
 */
enum NotificationType: string
{
    case AuctionWon = 'auction_won';
    case PaymentAuthorizationFailed = 'payment_authorization_failed';
    case TransferIssued = 'transfer_issued';
    case TransferConfirmed = 'transfer_confirmed';
    case TransferExpired = 'transfer_expired';
    case TransferCancelled = 'transfer_cancelled';
    case DisputeOpened = 'dispute_opened';
    case DisputeResolved = 'dispute_resolved';
}
