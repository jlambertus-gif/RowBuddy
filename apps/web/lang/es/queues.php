<?php

declare(strict_types=1);

return [
    'submission_succeeded' => 'Tu cola ha sido enviada para aprobación.',
    'blocked' => [
        'restricted_category' => 'Esta categoría no puede enviarse: está restringida y no es elegible en RowBuddy.',
        'jurisdiction_not_permitted' => 'RowBuddy aún no está disponible para esta categoría en el país seleccionado.',
    ],
    'errors' => [
        'account_suspended' => 'Tu cuenta no puede enviar colas mientras esté suspendida.',
    ],
    'fields' => [
        'category' => 'categoría',
        'jurisdiction_country' => 'país',
        'latitude' => 'latitud',
        'longitude' => 'longitud',
        'radius_meters' => 'radio (metros)',
        'reason' => 'motivo',
        'page' => 'página',
        'per_page' => 'resultados por página',
    ],
    'moderation' => [
        'not_found' => 'Cola no encontrada.',
        'invalid_transition' => 'Esta cola no puede pasar a ese estado en este momento.',
        'approved' => 'Cola aprobada.',
        'rejected' => 'Cola rechazada.',
        'published' => 'Cola publicada.',
    ],
];
