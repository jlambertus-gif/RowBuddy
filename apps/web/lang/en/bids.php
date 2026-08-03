<?php

declare(strict_types=1);

return [
    'fields' => [
        'amount' => 'bid amount',
        'currency' => 'currency',
    ],
    'errors' => [
        'auction_not_open' => 'This auction is no longer open for bidding.',
        'seller_cannot_bid' => 'You cannot bid on your own auction.',
        'bid_too_low' => 'Your bid must exceed the current highest bid.',
        'currency_mismatch' => 'Your bid currency does not match this auction.',
        'account_suspended' => 'Your account is suspended and cannot place bids.',
        'idempotency_key_required' => 'An Idempotency-Key header is required to place a bid.',
        'idempotency_key_reused' => 'This Idempotency-Key was already used for a different bid request.',
        'placement_in_progress' => 'A bid request with this Idempotency-Key is already being processed. You may safely retry this exact request using the same Idempotency-Key.',
    ],
];
