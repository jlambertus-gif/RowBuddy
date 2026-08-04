<?php

declare(strict_types=1);

return [
    'verify_email' => [
        'subject' => 'Verify your email address',
        'greeting' => 'Hello,',
        'body' => 'Please confirm your email address to finish setting up your RowBuddy account.',
        'action_label' => 'Verify email address',
        'footer' => "If you didn't create this account, no further action is required.",
    ],
    'reset_password' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello,',
        'body' => 'We received a request to reset the password for your RowBuddy account.',
        'action_label' => 'Reset password',
        'expires' => 'This link will expire in :count minutes.',
        'footer' => "If you didn't request a password reset, no further action is required.",
    ],
];
