<?php

declare(strict_types=1);

return [
    'auction_won' => [
        'subject' => 'Ganaste la subasta',
        'greeting' => 'Hola,',
        'body' => 'Felicidades — ganaste la subasta con referencia :reference con una oferta ganadora de :amount.',
        'next_step' => 'Ahora hay una autorización de pago pendiente contra tu método de pago guardado.',
    ],
    'payment_authorization_failed' => [
        'subject' => 'Acción requerida: falló la autorización de tu pago',
        'greeting' => 'Hola,',
        'body' => 'No pudimos autorizar el pago de tu subasta ganada con referencia :reference (monto :amount).',
        'next_step' => 'Actualiza tu método de pago o contacta a soporte para mantener tu subasta ganada activa.',
    ],
    'transfer_issued' => [
        'subject' => 'Tu transferencia está lista para confirmar',
        'greeting' => 'Hola,',
        'body' => 'Se abrió una ventana de transferencia para la referencia :reference.',
        'next_step' => 'Completa los pasos de confirmación en persona para finalizar la entrega.',
    ],
    'transfer_confirmed' => [
        'subject' => 'Tu transferencia ha sido confirmada',
        'greeting' => 'Hola,',
        'body' => 'Tu transferencia con referencia :reference ha sido confirmada.',
        'next_step' => 'Este es tu registro de confirmación duradero para esta transacción.',
    ],
    'transfer_expired' => [
        'subject' => 'Tu ventana de transferencia ha expirado',
        'greeting' => 'Hola,',
        'body' => 'La ventana de transferencia con referencia :reference expiró sin confirmación de ambas partes.',
        'next_step' => 'No se realizó ningún cargo. No se requiere ninguna acción.',
    ],
    'transfer_cancelled' => [
        'subject' => 'Tu transferencia ha sido cancelada',
        'greeting' => 'Hola,',
        'body' => 'La transferencia con referencia :reference ha sido cancelada.',
        'next_step' => 'No se realizó ningún cargo. No se requiere ninguna acción.',
    ],
    'dispute_opened' => [
        'subject' => 'Se ha presentado una disputa',
        'greeting' => 'Hola,',
        'body' => 'Se ha presentado una disputa con referencia :reference.',
        'next_step' => 'Responde con cualquier información relevante dentro del plazo de respuesta.',
    ],
    'dispute_resolved' => [
        'subject' => 'Tu disputa ha sido resuelta',
        'greeting' => 'Hola,',
        'body' => 'La disputa con referencia :reference ha sido resuelta: :outcome.',
        'refund_note' => 'Se ha emitido un reembolso de :amount.',
        'outcomes' => [
            'release_to_seller' => 'los fondos fueron liberados al vendedor',
            'refund_to_buyer' => 'el comprador ha sido reembolsado',
            'split' => 'se aplicó una resolución dividida',
            'cancelled' => 'la disputa fue cancelada',
        ],
    ],
];
