<?php

declare(strict_types=1);

return [
    'review' => [
        'not_found' => 'Dispute not found.',
        'note_required' => 'A note is required to record a correction.',
        'correction_recorded' => 'Correction recorded.',
    ],
    'fields' => [
        'note' => 'note',
    ],
    'filing' => [
        'fields' => [
            'reason' => 'reason',
        ],
        'errors' => [
            'not_found' => 'Transfer not found or not eligible for a dispute.',
            'access_denied' => 'You do not have access to this dispute.',
            'not_authorized' => 'Only the buyer on this transfer may open a dispute against it.',
            'already_exists' => 'A dispute already exists for this transfer.',
            'window_elapsed' => 'The filing window for this transfer has elapsed.',
        ],
    ],
];
