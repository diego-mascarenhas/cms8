<?php

return [
    'page_title' => 'Precios — Humano',
    'hero_title' => 'Planes y precios',
    'hero_subtitle' => 'Elige un plan y paga de forma segura con Stripe. Cambia entre facturación mensual y anual antes de ir al checkout.',
    'billing_monthly' => 'Mensual',
    'billing_annual' => 'Anual',
    'annual_discount_badge' => '30% menos',
    'save_hint' => 'Los planes anuales se cobran una vez al año.',
    'per_month_suffix' => '/mes',
    'per_year_suffix' => '/año',
    'billed_annually' => 'Facturación anual única.',
    'billed_monthly' => 'Facturación mensual.',
    'prices_plus_vat' => '+ I.V.A.',
    'subscribe' => 'Suscribirse',
    'coupon_title' => '¿Tienes un código de amigo?',
    'coupon_body' => 'Usa el código :code en el checkout cuando aparezca el campo de promoción y obtén un descuento del 50%!!!',
    'coupon_copy' => 'Copiar código',
    'coupon_copied' => 'Copiado',
    'staging_note' => 'Precios de staging: en producción se pueden cambiar los enlaces por variables de entorno.',
    'most_popular' => 'Más popular',

    'plans' => [
        'assistant' => [
            'name' => 'Humano.app Assistant',
            'description' => 'Déjate ayudar por la inteligencia artificial para automatizar procesos rutinarios de tu negocio, como la gestión a través de WhatsApp de la agenda, los clientes y las tareas manteniendo tu propia identidad.',
            'features' => [
                'Asistente centrado en WhatsApp con tu tono de marca',
                'Agenda, clientes y tareas del día a día en un solo flujo',
                'Automatización de lo repetitivo para ganar tiempo',
                'Canales digitales para seguir cerca de tus clientes',
            ],
        ],
        'business' => [
            'name' => 'Humano.app Business',
            'description' => 'Controla toda la parte digital de tu negocio en una sola plataforma gestionada con inteligencia artificial y sin perder el toque humano que te diferencia de la competencia.',
            'features' => [
                'Todo lo de Assistant, más una vista digital unificada',
                'IA que apoya decisiones sin sustituir tu estilo',
                'Espacio para crecer con tu equipo y tus procesos',
                'Visibilidad operativa de conjunto',
            ],
        ],
        'foundation' => [
            'name' => 'Humano.app Foundation',
            'description' => 'La solución completa para automatizar todo tu negocio y hacerlo crecer más allá de lo que jamás imaginaste sin necesidad de tener que contratar a más personal.',
            'features' => [
                'Automatización integral adaptada a cómo trabajas',
                'Escala ingresos y capacidad sin crecer a la fuerza en plantilla',
                'Acompañamiento prioritario en despliegues complejos',
                'Una base pensada para sumar valor con el tiempo',
            ],
        ],
    ],

    'checkout_complete_success' => '¡Bienvenido! Tu espacio de trabajo ya está listo.',
    'checkout_complete_invalid_session' => 'No pudimos confirmar este pago. Abrí el enlace del recibo de Stripe o contactá soporte.',
    'checkout_complete_not_paid' => 'Este checkout aún no está completado o pagado.',
    'checkout_complete_unsupported_mode' => 'Este tipo de checkout no admite alta automática.',
    'checkout_complete_no_email' => 'Stripe no proporcionó un email para este pago, así que no podemos crear tu cuenta.',
    'checkout_complete_no_customer' => 'Stripe no devolvió un cliente para esta sesión.',
    'checkout_complete_no_team' => 'No pudimos asociar este pago a un espacio de trabajo. Contactá soporte.',
    'checkout_complete_customer_mismatch' => 'Este pago corresponde a otro cliente de Stripe que el de tu espacio actual. Iniciá sesión con la cuenta del email del pagador o contactá soporte.',
    'checkout_complete_register_first' => 'Primero creá tu cuenta y luego completá el pago desde el paso de facturación.',

    'checkout_billing_gate_pending' => 'Recibimos el pago, pero aún no pudimos validar el acceso en la app. Copiá este mensaje y contactá soporte, o probá de nuevo en unos minutos.',
];
