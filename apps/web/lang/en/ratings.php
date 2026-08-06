<?php

declare(strict_types=1);

return [
    'fields' => [
        'score' => 'score',
        'comment' => 'comment',
    ],
    'errors' => [
        'not_found' => 'Transfer not found or not eligible for a rating.',
        'access_denied' => 'You do not have access to this transfer.',
        'account_suspended' => 'Your account cannot submit ratings while suspended.',
        'already_rated' => 'You have already submitted a rating for this transfer.',
        'invalid_score' => 'The rating score must be between 1 and 5.',
        'comment_too_long' => 'The comment is too long.',
    ],
];
