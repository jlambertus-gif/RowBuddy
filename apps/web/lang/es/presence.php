<?php

declare(strict_types=1);

return [
    'fields' => [
        'queue_id' => 'cola',
        'latitude' => 'latitud',
        'longitude' => 'longitud',
        'accuracy_meters' => 'precisión (metros)',
    ],
    'errors' => [
        'not_found' => 'Sesión de presencia no encontrada.',
        'access_denied' => 'Esta sesión de presencia no te pertenece.',
        'queue_unavailable' => 'Esta cola no está disponible para captura de presencia.',
        'duplicate_active_session' => 'Ya tienes una sesión de presencia activa para esta cola.',
        'not_active' => 'Esta sesión de presencia ya ha finalizado.',
    ],
];
