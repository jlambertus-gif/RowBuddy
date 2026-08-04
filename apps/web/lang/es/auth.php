<?php

declare(strict_types=1);

return [
    'verify_email' => [
        'subject' => 'Verifica tu correo electrónico',
        'greeting' => 'Hola,',
        'body' => 'Confirma tu correo electrónico para terminar de configurar tu cuenta de RowBuddy.',
        'action_label' => 'Verificar correo electrónico',
        'footer' => 'Si no creaste esta cuenta, no es necesario que hagas nada más.',
    ],
    'reset_password' => [
        'subject' => 'Restablece tu contraseña',
        'greeting' => 'Hola,',
        'body' => 'Recibimos una solicitud para restablecer la contraseña de tu cuenta de RowBuddy.',
        'action_label' => 'Restablecer contraseña',
        'expires' => 'Este enlace vencerá en :count minutos.',
        'footer' => 'Si no solicitaste restablecer tu contraseña, no es necesario que hagas nada más.',
    ],
];
