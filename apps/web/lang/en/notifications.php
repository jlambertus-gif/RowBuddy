<?php

declare(strict_types=1);

return [
    'auction_won' => [
        'subject' => 'You won the auction',
        'greeting' => 'Hello,',
        'body' => 'Congratulations — you won the auction for reference :reference with a winning bid of :amount.',
        'next_step' => 'A payment authorization is now pending against your saved payment method.',
    ],
    'payment_authorization_failed' => [
        'subject' => 'Action needed: your payment authorization failed',
        'greeting' => 'Hello,',
        'body' => 'We were unable to authorize payment for your win on reference :reference (amount :amount).',
        'next_step' => 'Please update your payment method or contact support to keep your win active.',
    ],
    'transfer_issued' => [
        'subject' => 'Your transfer is ready to confirm',
        'greeting' => 'Hello,',
        'body' => 'A transfer window has opened for reference :reference.',
        'next_step' => 'Please complete the confirmation steps in person to finalize the handoff.',
    ],
    'transfer_confirmed' => [
        'subject' => 'Your transfer has been confirmed',
        'greeting' => 'Hello,',
        'body' => 'Your transfer for reference :reference has been confirmed.',
        'next_step' => 'This is your durable confirmation record for this transaction.',
    ],
    'transfer_expired' => [
        'subject' => 'Your transfer window has expired',
        'greeting' => 'Hello,',
        'body' => 'The transfer window for reference :reference has expired without confirmation from both parties.',
        'next_step' => 'No charge was made. No action is required.',
    ],
    'transfer_cancelled' => [
        'subject' => 'Your transfer has been cancelled',
        'greeting' => 'Hello,',
        'body' => 'The transfer for reference :reference has been cancelled.',
        'next_step' => 'No charge was made. No action is required.',
    ],
    'dispute_opened' => [
        'subject' => 'A dispute has been filed',
        'greeting' => 'Hello,',
        'body' => 'A dispute has been filed for reference :reference.',
        'next_step' => 'Please respond with any relevant information within the response window.',
    ],
    'dispute_resolved' => [
        'subject' => 'Your dispute has been resolved',
        'greeting' => 'Hello,',
        'body' => 'The dispute for reference :reference has been resolved: :outcome.',
        'refund_note' => 'A refund of :amount has been issued.',
        'outcomes' => [
            'release_to_seller' => 'funds released to the seller',
            'refund_to_buyer' => 'the buyer has been refunded',
            'split' => 'a split resolution was applied',
            'cancelled' => 'the dispute was cancelled',
        ],
    ],
];
