<?php

declare(strict_types=1);

return [
    'fields' => [
        'queue_id' => 'queue',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'accuracy_meters' => 'accuracy (meters)',
    ],
    'errors' => [
        'not_found' => 'Presence session not found.',
        'access_denied' => 'This presence session does not belong to you.',
        'queue_unavailable' => 'This queue is not available for presence capture.',
        'duplicate_active_session' => 'You already have an active presence session for this queue.',
        'not_active' => 'This presence session has already ended.',
    ],
];
