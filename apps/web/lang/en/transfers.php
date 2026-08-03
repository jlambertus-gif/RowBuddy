<?php

declare(strict_types=1);

return [
    'fields' => [
        'qr_token' => 'confirmation code',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
    ],
    'errors' => [
        'not_found' => 'Transfer not found.',
        'access_denied' => 'You do not have access to this transfer.',
        'invalid_qr_token' => 'That confirmation code does not match.',
        'outside_geofence' => 'You must be at the queue location to confirm this transfer.',
        'illegal_state' => 'This transfer can no longer be confirmed.',
        'qr_token_unavailable' => 'The confirmation code is no longer available.',
    ],
];
