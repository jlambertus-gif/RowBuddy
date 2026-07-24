<?php

declare(strict_types=1);

return [
    'submission_succeeded' => 'Your queue has been submitted for approval.',
    'blocked' => [
        'restricted_category' => 'This category cannot be submitted: it is restricted and not eligible on RowBuddy.',
        'jurisdiction_not_permitted' => 'RowBuddy is not yet available for this category in the selected country.',
    ],
    'fields' => [
        'category' => 'category',
        'jurisdiction_country' => 'country',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'radius_meters' => 'radius (meters)',
        'reason' => 'reason',
        'page' => 'page',
        'per_page' => 'results per page',
    ],
    'moderation' => [
        'not_found' => 'Queue not found.',
        'invalid_transition' => 'This queue cannot be moved to that state right now.',
        'approved' => 'Queue approved.',
        'rejected' => 'Queue rejected.',
        'published' => 'Queue published.',
    ],
];
