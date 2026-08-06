<?php

declare(strict_types=1);

return [
    'review' => [
        'not_found' => 'Disputa no encontrada.',
        'note_required' => 'Se requiere una nota para registrar una corrección.',
        'correction_recorded' => 'Corrección registrada.',
    ],
    'fields' => [
        'note' => 'nota',
    ],
    'filing' => [
        'fields' => [
            'reason' => 'motivo',
        ],
        'errors' => [
            'not_found' => 'Transferencia no encontrada o no elegible para una disputa.',
            'access_denied' => 'No tienes acceso a esta disputa.',
            'not_authorized' => 'Solo el comprador de esta transferencia puede abrir una disputa.',
            'already_exists' => 'Ya existe una disputa para esta transferencia.',
            'window_elapsed' => 'El plazo para presentar una disputa sobre esta transferencia ha expirado.',
        ],
    ],
];
