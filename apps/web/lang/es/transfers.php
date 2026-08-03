<?php

declare(strict_types=1);

return [
    'fields' => [
        'qr_token' => 'código de confirmación',
        'latitude' => 'latitud',
        'longitude' => 'longitud',
    ],
    'errors' => [
        'not_found' => 'Transferencia no encontrada.',
        'access_denied' => 'No tienes acceso a esta transferencia.',
        'invalid_qr_token' => 'Ese código de confirmación no coincide.',
        'outside_geofence' => 'Debes estar en la ubicación de la fila para confirmar esta transferencia.',
        'illegal_state' => 'Esta transferencia ya no se puede confirmar.',
        'qr_token_unavailable' => 'El código de confirmación ya no está disponible.',
    ],
];
