<?php

return [
    'issue_title' => 'Nota de crédito',
    'issue_button' => 'Emitir en Stripe',
    'reason' => 'Motivo',
    'confirm' => '¿Emitir la nota de crédito en Stripe para esta factura?',
    'success' => 'Nota de crédito :reference emitida en Stripe.',
    'reasons' => [
        'duplicate' => 'Duplicado',
        'fraudulent' => 'Fraudulento',
        'order_change' => 'Cambio de pedido',
        'product_unsatisfactory' => 'Producto insatisfactorio',
    ],
    'errors' => [
        'not_allowed' => 'No puedes emitir una nota de crédito para esta factura.',
        'team_not_found' => 'No se encontró el equipo de la factura.',
        'stripe_not_configured' => 'Configura la clave secreta de Stripe en los ajustes del equipo.',
    ],
];
