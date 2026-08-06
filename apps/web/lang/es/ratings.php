<?php

declare(strict_types=1);

return [
    'fields' => [
        'score' => 'puntuación',
        'comment' => 'comentario',
    ],
    'errors' => [
        'not_found' => 'Transferencia no encontrada o no elegible para calificación.',
        'access_denied' => 'No tienes acceso a esta transferencia.',
        'account_suspended' => 'Tu cuenta no puede enviar calificaciones mientras esté suspendida.',
        'already_rated' => 'Ya has enviado una calificación para esta transferencia.',
        'invalid_score' => 'La puntuación debe estar entre 1 y 5.',
        'comment_too_long' => 'El comentario es demasiado largo.',
    ],
];
