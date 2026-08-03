<?php

declare(strict_types=1);

return [
    'fields' => [
        'amount' => 'monto de la puja',
        'currency' => 'moneda',
    ],
    'errors' => [
        'auction_not_open' => 'Esta subasta ya no está abierta para pujas.',
        'seller_cannot_bid' => 'No puedes pujar en tu propia subasta.',
        'bid_too_low' => 'Tu puja debe superar la puja más alta actual.',
        'currency_mismatch' => 'La moneda de tu puja no coincide con la de esta subasta.',
        'account_suspended' => 'Tu cuenta está suspendida y no puede realizar pujas.',
        'idempotency_key_required' => 'Se requiere un encabezado Idempotency-Key para realizar una puja.',
        'idempotency_key_reused' => 'Esta Idempotency-Key ya se usó para una solicitud de puja diferente.',
        'placement_in_progress' => 'Ya se está procesando una solicitud de puja con esta Idempotency-Key. Puedes reintentar esta misma solicitud de forma segura usando la misma Idempotency-Key.',
    ],
];
