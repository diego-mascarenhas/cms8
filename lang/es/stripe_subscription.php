<?php

return [
    'title' => 'Suscripciones',
    'subtitle' => 'Suscripciones de clientes sincronizadas desde Stripe',
    'sync_button' => 'Sincronizar desde Stripe',
    'sync_success' => 'Suscripciones sincronizadas desde Stripe: :count procesadas.',
    'errors' => [
        'no_team' => 'Equipo no seleccionado.',
        'no_stripe_secret' => 'Configura la clave secreta de Stripe en Ajustes del equipo para poder sincronizar. Puedes usar la API de test (claves que empiezan por sk_test_).',
    ],
    'columns' => [
        'customer_name' => 'Cliente',
        'customer_email' => 'Correo electrónico',
        'plan_name' => 'Plan',
        'status' => 'Estado',
        'amount_total' => 'Importe',
        'current_period_end' => 'Próxima',
        'actions' => 'Acciones',
    ],
    'open_service' => 'Ver servicio',
    'open_client' => 'Ver cliente',
    'open_contact' => 'Ver contacto',
    'status' => [
        'active' => 'Activa',
        'canceled' => 'Cancelada',
        'incomplete' => 'Incompleta',
        'incomplete_expired' => 'Incompleta (expirada)',
        'past_due' => 'Pago atrasado',
        'paused' => 'Pausada',
        'trialing' => 'Periodo de prueba',
        'unpaid' => 'Impaga',
    ],
];
