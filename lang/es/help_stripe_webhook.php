<?php

return [
    'page_title' => 'Webhooks de Stripe — Ayuda',
    'title' => 'Webhooks de Stripe (Humano)',
    'intro' => 'En el panel de Stripe (Developers → Webhooks), crea un destino que envíe eventos a esta aplicación. Humano usa Laravel Cashier y controladores propios para suscripciones, facturas y comisiones de afiliados.',

    'url_heading' => 'URL del endpoint',
    'url_method' => 'Método: POST',
    'url_path_label' => 'Ruta en tu sitio',
    'url_full_example' => 'URL completa (sustituye el dominio por el público de tu entorno, alineado con APP_URL):',
    'url_https' => 'En staging y producción Stripe debe poder llamar a tu URL por HTTPS.',

    'local_heading' => 'Desarrollo local',
    'local_body' => 'Stripe no puede llegar a https://*.test desde internet. Usa Stripe CLI y el signing secret que muestra (por ejemplo en STRIPE_WEBHOOK_SECRET):',

    'multi_heading' => 'Opcional: cuentas Stripe por categoría',
    'multi_body' => 'Si usas cuentas Stripe distintas por categoría, configura un endpoint por categoría. Valores permitidos: mentoring, mailer, prospecting, hosting, support. Ejemplo:',

    'events_heading' => 'Eventos a habilitar',
    'events_intro' => 'Selecciona como mínimo los siguientes. Cubren la sincronización de Cashier, tu lógica de facturas y cambios de plan:',

    'events_recommended_heading' => 'También recomendados',

    'events_required' => [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
    ],

    'events_recommended' => [
        'customer.updated',
        'payment_method.automatically_updated',
    ],

    'events_checkout' => 'Si la app crea sesiones de Stripe Checkout (suscripciones o registro con pago), añade también:',
    'events_checkout_item' => 'checkout.session.completed',

    'scope_heading' => 'Alcance del destino y versión de API',
    'scope_body' => 'Usa “Your account” salvo que operes como plataforma Stripe Connect y recibas eventos de cuentas conectadas. Elige una versión de API compatible con tu SDK; si el payload difiere, alinea la versión del dashboard con tu proyecto.',

    'secret_heading' => 'Signing secret',
    'secret_body' => 'Tras crear el endpoint, copia el signing secret en STRIPE_WEBHOOK_SECRET (o en stripe_accounts.{categoría}.webhook_secret para URLs por categoría). La ruta está excluida de verificación CSRF.',
];
