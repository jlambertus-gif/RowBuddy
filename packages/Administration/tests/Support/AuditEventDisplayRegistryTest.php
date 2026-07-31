<?php

declare(strict_types=1);

use RowBuddy\Administration\Support\AuditEventDisplayRegistry;

const REGISTERED_AUDIT_EVENT_NAMES = [
    'bids.bid_placed',
    'ratings.rating_submitted',
    'auctions.auction_opened',
    'auctions.auction_won',
    'auctions.auction_proximity_at_risk',
    'auctions.auction_proximity_restored',
    'auctions.auction_closing_started',
    'auctions.auction_closing_deadline_extended',
    'auctions.auction_expired',
    'auctions.auction_cancelled',
    'transfers.transfer_issued',
    'transfers.transfer_seller_confirmed',
    'transfers.transfer_buyer_confirmed',
    'transfers.transfer_confirmed',
    'transfers.transfer_evidence_attached',
    'transfers.transfer_expired',
    'transfers.transfer_cancelled',
    'disputes.dispute_opened',
    'disputes.dispute_evidence_attached',
    'disputes.dispute_resolved',
    'queue_presence.presence_session_started',
    'queue_presence.evidence_photo_recorded',
    'queue_presence.presence_confidence_computed',
    'queue_presence.presence_session_ended',
    'queues.queue_submitted_for_approval',
    'queues.queue_approved',
    'queues.queue_rejected',
    'queues.queue_published',
    'payments.payment_authorized',
    'payments.payment_authorization_failed',
    'payments.payment_captured',
    'payments.payment_capture_failed',
    'payments.payment_refunded',
    'payments.authorization_cancelled',
    'payments.stripe_webhook_event_processed',
    'payments.seller_payout_account_linked',
];

it('registers every auditable event type produced anywhere in this codebase', function () {
    $registry = new AuditEventDisplayRegistry;

    foreach (REGISTERED_AUDIT_EVENT_NAMES as $eventName) {
        expect($registry->isRegistered($eventName))->toBeTrue("Expected [{$eventName}] to be registered.");
    }
});

it('is fail-closed for an unregistered event type: not registered, and filter returns nothing regardless of payload', function () {
    $registry = new AuditEventDisplayRegistry;

    expect($registry->isRegistered('some_future_module.some_new_event'))->toBeFalse()
        ->and($registry->filter('some_future_module.some_new_event', ['secret' => 'value', 'id' => '1']))->toBe([]);
});

it('strips the dispute evidence storage_reference even though it is present in the stored payload', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('disputes.dispute_evidence_attached', [
        'dispute_id' => 'dispute-1',
        'type' => 'photo',
        'storage_reference' => 'disputes/dispute-1/private-photo.jpg',
        'submitted_by' => '101',
    ]);

    expect($filtered)->toBe(['dispute_id' => 'dispute-1', 'type' => 'photo', 'submitted_by' => '101'])
        ->and($filtered)->not->toHaveKey('storage_reference');
});

it('strips the transfer evidence storage_reference even though it is present in the stored payload', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('transfers.transfer_evidence_attached', [
        'transfer_id' => 'transfer-1',
        'type' => 'photo',
        'storage_reference' => 'transfers/transfer-1/private-photo.jpg',
        'submitted_by' => '101',
    ]);

    expect($filtered)->not->toHaveKey('storage_reference');
});

it('strips the queue presence evidence_reference even though it is present in the stored payload', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('queue_presence.evidence_photo_recorded', [
        'presence_session_id' => 'session-1',
        'evidence_reference' => 'presence/session-1/private-photo.jpg',
    ]);

    expect($filtered)->toBe(['presence_session_id' => 'session-1'])
        ->and($filtered)->not->toHaveKey('evidence_reference');
});

it('strips the Stripe account id even though it is present in the stored payload', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('payments.seller_payout_account_linked', [
        'seller_id' => '101',
        'stripe_account_id' => 'acct_1234567890',
    ]);

    expect($filtered)->toBe(['seller_id' => '101'])
        ->and($filtered)->not->toHaveKey('stripe_account_id');
});

it('strips the Stripe event id even though it is present in the stored payload', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('payments.stripe_webhook_event_processed', [
        'stripe_event_id' => 'evt_1234567890',
        'event_type' => 'payment_intent.succeeded',
    ]);

    expect($filtered)->toBe(['event_type' => 'payment_intent.succeeded'])
        ->and($filtered)->not->toHaveKey('stripe_event_id');
});

it('preserves every allowed field with its original value for a representative event', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('disputes.dispute_resolved', [
        'dispute_id' => 'dispute-1',
        'outcome' => 'refund_to_buyer',
        'refund_amount_minor_units' => 5000,
        'refund_amount_currency' => 'USD',
        'resolved_by' => '999',
        'resolution_notes' => 'Evidence supported the buyer.',
        'evidence_found_fraudulent' => false,
    ]);

    expect($filtered)->toBe([
        'dispute_id' => 'dispute-1',
        'outcome' => 'refund_to_buyer',
        'refund_amount_minor_units' => 5000,
        'refund_amount_currency' => 'USD',
        'resolved_by' => '999',
        'resolution_notes' => 'Evidence supported the buyer.',
        'evidence_found_fraudulent' => false,
    ]);
});

it('silently ignores an allowed key that happens to be missing from the stored payload', function () {
    $registry = new AuditEventDisplayRegistry;

    $filtered = $registry->filter('auctions.auction_won', [
        'auction_id' => 'auction-1',
        'winning_bid_id' => 'bid-1',
    ]);

    expect($filtered)->toBe(['auction_id' => 'auction-1', 'winning_bid_id' => 'bid-1']);
});
