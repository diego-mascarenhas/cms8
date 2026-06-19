<?php

return [
    'page_title' => 'Insight diario · Tu prioridad de equipo cada mañana',
    'meta_description' => 'Humano resume WhatsApp, correo, tareas, facturas y citas en un informe diario con IA. Actúa con respuestas sugeridas y envíos programados.',

    'nav' => [
        'metrics' => 'Métricas',
        'product' => 'Producto',
        'features' => 'Funciones',
        'guide' => 'Guía',
        'cta' => 'Empezar',
        'login' => 'Entrar',
    ],

    'hero' => [
        'eyebrow' => 'Performance Insight · IA + operaciones',
        'title' => 'Sabe qué importa hoy antes del primer café',
        'lead' => 'Cada mañana recibes un titular, un foco en cinco palabras y un mensaje accionable. WhatsApp sin leer, facturas vencidas, tareas del día y citas: todo en un solo insight.',
        'cta_secondary' => 'Ver guía interactiva',
        'cta_newsletter' => 'Pieza newsletter',
    ],

    'metrics' => [
        'eyebrow' => 'Datos del equipo',
        'title' => 'Métricas que alimentan el insight',
        'lead' => 'Humano cruza señales de los módulos activos y calcula un ratio de rendimiento (0–100) para orientar el foco del día.',
        'ratio_label' => 'Ratio de rendimiento',
        'ratio_value' => '78',
        'chart_title' => 'Actividad últimos 7 días',
        'chart_days' => ['L', 'M', 'X', 'J', 'V', 'S', 'D'],
        'highlights_title' => 'Señales monitorizadas',
        'highlights' => [
            ['label' => 'WhatsApp sin leer', 'value' => '12', 'pct' => 85],
            ['label' => 'Correos pendientes', 'value' => '8', 'pct' => 62],
            ['label' => 'Tareas vencidas', 'value' => '3', 'pct' => 40],
            ['label' => 'Facturas impagadas', 'value' => '5', 'pct' => 55],
            ['label' => 'Citas hoy', 'value' => '4', 'pct' => 30],
        ],
    ],

    'dashboard' => [
        'eyebrow' => 'Panel',
        'title' => 'Tarjeta de insight en el dashboard',
        'lead' => 'El administrador abre Humano y ve el informe del día: titular, foco, mensaje y los puntos clave del resumen.',
        'headline' => '⚡ Enfócate',
        'focus' => 'Cobrar facturas vencidas hoy',
        'message' => 'Tienes 5 facturas impagadas y 3 tareas vencidas. Prioriza el seguimiento a IDONEO y responde los WhatsApp de facturación antes del mediodía.',
        'highlights' => [
            '5 facturas con saldo pendiente',
            '8 correos sin leer en buzón',
            '4 citas programadas para hoy',
        ],
    ],

    'notification' => [
        'eyebrow' => 'Acción',
        'title' => 'Respuestas sugeridas y envío programado',
        'lead' => 'Desde la notificación expandes cada highlight, copias la respuesta sugerida o programas el envío por correo o WhatsApp en 2 horas.',
        'preview_from' => 'contabilidad@idoneo.dev',
        'preview_subject' => 'Re: Factura pendiente — IDONEO',
        'preview_body' => 'Buenos días, adjuntamos el detalle de la factura pendiente…',
        'suggestion' => 'Hola Laura. Te escribo por la factura F-IDO-2026-01 vencida (4.820,00 €). ¿Podemos coordinar el pago esta semana?',
        'schedule_email' => 'Programar correo (2 h)',
        'scheduled_badge' => 'Programado para 19/06/2026 12:44',
        'cancel' => 'Desprogramar',
    ],

    'channels' => [
        'eyebrow' => 'Canales',
        'title' => 'Email, campana y dashboard',
        'lead' => 'El mismo insight llega por correo Markdown, notificación in-app y tarjeta del panel. Historial filtrable de los últimos 60 días.',
        'email' => 'Correo matutino',
        'bell' => 'Notificación in-app',
        'card' => 'Tarjeta dashboard',
        'history' => 'Historial 60 días',
    ],

    'features' => [
        'eyebrow' => 'Qué incluye',
        'title' => 'Informe listo para actuar',
        'items' => [
            [
                'title' => 'IA + plantillas de respaldo',
                'text' => 'Titular, foco y mensaje generados con Anthropic; si falla la IA, entran plantillas de emergencia en español.',
            ],
            [
                'title' => 'Contexto de facturas',
                'text' => 'Detecta saldos pendientes del contacto y sugiere respuestas con número de factura e importe.',
            ],
            [
                'title' => 'Programar en 2 horas',
                'text' => 'Envía la respuesta sugerida más tarde sin salir de la notificación; cancela si cambia la prioridad.',
            ],
            [
                'title' => 'Comando /generar-insight',
                'text' => 'Regenera el insight manualmente desde el asistente web o WhatsApp cuando lo necesites.',
            ],
        ],
    ],

    'cta' => [
        'title' => '¿Listo para empezar el día con claridad?',
        'lead' => 'Activa el módulo Performance Insights en tu equipo y recibe el primer informe mañana a las 06:15.',
        'button' => 'Quiero el insight diario',
        'secondary' => 'Ver presentación',
    ],

    'lead' => [
        'sources' => [
            'hero' => 'Insight diario landing · hero',
            'cta' => 'Insight diario landing · CTA',
        ],
    ],

    'newsletter' => [
        'page_title' => 'Newsletter · Insight diario Humano',
        'preview_note' => 'Vista previa de la pieza para campañas. Copiá el HTML o usala como referencia en el editor de plantillas.',
        'subject' => 'Nuevo: tu informe diario de operaciones en Humano',
        'preheader' => 'Titular, foco y acciones concretas cada mañana — WhatsApp, correo, tareas y facturas.',
        'headline' => 'Empieza el día sabiendo qué importa',
        'intro' => 'Performance Insight resume la operativa de tu equipo en un mensaje claro: qué revisar, a quién responder y qué cobrar. Con respuestas sugeridas y envío programado en dos horas.',
        'admin_title' => 'Para vos (administrador)',
        'admin_bullets' => [
            'Ratio de rendimiento y resumen de highlights del día',
            'Correo + notificación + tarjeta en el dashboard',
            'Respuestas sugeridas con contexto de facturas y citas',
        ],
        'user_title' => 'Señales que cruza',
        'user_bullets' => [
            'WhatsApp y correo sin leer',
            'Tareas vencidas y citas del día',
            'Facturas impagadas y clientes estresados',
        ],
        'cta' => 'Conocer Insight diario',
        'cta_guide' => 'Ver guía',
        'footer' => 'Humano · CRM, operaciones y rendimiento en un solo lugar.',
        'badge' => 'Performance Insight',
        'ratio_label' => 'Ratio hoy',
        'ratio_value' => '78/100',
        'focus_label' => 'Foco del día',
        'focus_value' => 'Cobrar facturas vencidas hoy',
    ],
];
