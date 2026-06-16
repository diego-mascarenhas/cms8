<?php

return [
    'sync_button' => 'Sincronizar',
    'sync_success' => 'Facturas sincronizadas desde :providers. :imported nuevas, :updated actualizadas.',
    'sync_warning_skipped' => 'Sincronizado desde :providers pero :skipped factura(s) no se importaron (faltan datos del cliente/proveedor).',
    'errors' => [
        'no_team' => 'No hay equipo seleccionado.',
        'nothing_configured' => 'Configura Stripe o Cuéntica en los ajustes del equipo para sincronizar facturas.',
    ],
    'providers' => [
        'stripe' => 'Stripe',
        'cuentica' => 'Cuéntica',
    ],
];
