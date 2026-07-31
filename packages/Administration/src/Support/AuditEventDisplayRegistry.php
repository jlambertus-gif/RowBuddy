<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Support;

/**
 * The explicit, per-event-type admin-display allowlist ADR-026 §6
 * requires — one entry per `AuditableAction::eventName()` value produced
 * anywhere in this codebase, fixed in code, keyed by the same plain
 * string every event already uses (never the event's own class), so
 * this package never depends on any producing module directly.
 *
 * A new auditable event type is invisible in the admin audit log until
 * it gets its own entry here — that is the fail-closed behavior this
 * decision requires, not a bug: {@see isRegistered()} returns false and
 * {@see fieldsFor()} returns an empty allowlist for anything not listed
 * below.
 *
 * Every entry was hand-reviewed against its event's own `payload()`
 * method to exclude exactly the categories ADR-026 Architecture
 * Refinements §7 names: private evidence/storage references
 * (`storage_reference`, `evidence_reference`) and Stripe identifiers
 * (`stripe_account_id`, `stripe_event_id`). Everything else in a given
 * event's payload — ids, amounts, statuses, reasons, geo coordinates,
 * free-text notes/comments — is ordinary business fact already visible
 * elsewhere in the product, not an infrastructure/security detail, so it
 * is allowed through.
 */
final class AuditEventDisplayRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWLISTS = [
        // Bids
        'bids.bid_placed' => ['bid_id', 'auction_id', 'bidder_id', 'amount_minor_units', 'amount_currency'],

        // Ratings
        'ratings.rating_submitted' => ['rating_id', 'transfer_id', 'rater_id', 'ratee_id', 'score', 'comment'],

        // Auctions
        'auctions.auction_opened' => ['auction_id', 'queue_id', 'seller_id', 'presence_session_id', 'starting_price_minor_units', 'starting_price_currency'],
        'auctions.auction_won' => ['auction_id', 'winning_bid_id', 'winning_amount_minor_units', 'winning_amount_currency'],
        'auctions.auction_proximity_at_risk' => ['auction_id'],
        'auctions.auction_proximity_restored' => ['auction_id'],
        'auctions.auction_closing_started' => ['auction_id'],
        'auctions.auction_closing_deadline_extended' => ['auction_id', 'new_closes_at'],
        'auctions.auction_expired' => ['auction_id'],
        'auctions.auction_cancelled' => ['auction_id'],

        // Transfers
        'transfers.transfer_issued' => ['transfer_id', 'auction_id', 'winning_bid_id', 'seller_id', 'buyer_id'],
        'transfers.transfer_seller_confirmed' => ['transfer_id', 'latitude', 'longitude'],
        'transfers.transfer_buyer_confirmed' => ['transfer_id', 'latitude', 'longitude'],
        'transfers.transfer_confirmed' => ['transfer_id', 'auction_id', 'winning_bid_id'],
        'transfers.transfer_evidence_attached' => ['transfer_id', 'type', 'submitted_by'],
        'transfers.transfer_expired' => ['transfer_id', 'auction_id'],
        'transfers.transfer_cancelled' => ['transfer_id', 'auction_id', 'reason'],

        // Disputes
        'disputes.dispute_opened' => ['dispute_id', 'transfer_id', 'auction_id', 'buyer_id', 'seller_id', 'reason'],
        'disputes.dispute_evidence_attached' => ['dispute_id', 'type', 'submitted_by'],
        'disputes.dispute_resolved' => ['dispute_id', 'outcome', 'refund_amount_minor_units', 'refund_amount_currency', 'resolved_by', 'resolution_notes', 'evidence_found_fraudulent'],

        // Queue Presence
        'queue_presence.presence_session_started' => ['presence_session_id', 'queue_id', 'seller_id'],
        'queue_presence.evidence_photo_recorded' => ['presence_session_id'],
        'queue_presence.presence_confidence_computed' => ['presence_session_id', 'previous_tier', 'new_tier', 'points'],
        'queue_presence.presence_session_ended' => ['presence_session_id'],

        // Queues
        'queues.queue_submitted_for_approval' => ['queue_id', 'submitted_by_user_id'],
        'queues.queue_approved' => ['queue_id', 'approved_by_user_id'],
        'queues.queue_rejected' => ['queue_id', 'rejected_by_user_id', 'reason'],
        'queues.queue_published' => ['queue_id'],

        // Payments
        'payments.payment_authorized' => ['payment_intent_id', 'auction_id', 'winning_bid_id', 'amount_minor_units', 'amount_currency', 'fee_amount_minor_units'],
        'payments.payment_authorization_failed' => ['payment_intent_id', 'auction_id', 'winning_bid_id', 'amount_minor_units', 'amount_currency', 'reason'],
        'payments.payment_captured' => ['payment_intent_id', 'auction_id'],
        'payments.payment_capture_failed' => ['payment_intent_id', 'auction_id', 'reason'],
        'payments.payment_refunded' => ['payment_intent_id', 'auction_id', 'amount_minor_units', 'amount_currency', 'reason'],
        'payments.authorization_cancelled' => ['payment_intent_id', 'auction_id', 'reason'],
        'payments.stripe_webhook_event_processed' => ['event_type'],
        'payments.seller_payout_account_linked' => ['seller_id'],
    ];

    public function isRegistered(string $eventName): bool
    {
        return array_key_exists($eventName, self::ALLOWLISTS);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function filter(string $eventName, array $payload): array
    {
        if (! $this->isRegistered($eventName)) {
            return [];
        }

        return array_intersect_key($payload, array_flip(self::ALLOWLISTS[$eventName]));
    }
}
