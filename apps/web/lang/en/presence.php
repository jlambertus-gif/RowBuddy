<?php

declare(strict_types=1);

return [
    'fields' => [
        'queue_id' => 'queue',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'accuracy_meters' => 'accuracy (meters)',
        'photo' => 'photo',
    ],
    'errors' => [
        'not_found' => 'Presence session not found.',
        'access_denied' => 'This presence session does not belong to you.',
        'queue_unavailable' => 'This queue is not available for presence capture.',
        'duplicate_active_session' => 'You already have an active presence session for this queue.',
        'not_active' => 'This presence session has already ended.',
        'invalid_photo' => 'The uploaded photo could not be processed. Please try another one.',
    ],
];
