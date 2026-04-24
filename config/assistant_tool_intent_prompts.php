<?php

/**
 * When enabled, the tool-enabled assistant (WhatsApp + API chat_assistant) can merge an extra
 * instruction block from module_prompts for the active flow.
 *
 * Default (team setting off): routing is LLM-driven — discovery list includes routing keys built from
 * each active prompt's module key + section_key; the model calls commit_assistant_flow. No automatic keyword router.
 * When a team enables "keyword intent routing" in team settings (Chat / Asistente): phrase/word scoring
 * (config + optional section_key) can attach a flow without the LLM choosing. Default is off (LLM + commit_assistant_flow).
 * Create one active prompt per team
 * using one of the routing_keys listed under each intent (same keys as Prompt::findByRoutingKey).
 * When keyword routing is on, if no config intent matches, {@see \App\Services\AssistantToolIntentPromptService::findPromptBySectionKeyKeywords}
 * also scores the message against each active prompt's section_key (underscores as spaces; word tokens).
 *
 * @see \App\Services\AssistantToolIntentPromptService
 */
return [

    'enabled' => (bool) env('ASSISTANT_TOOL_INTENT_PROMPTS', true),

    /** Minimum score to attach a flow prompt (phrase = +2, word = +1). */
    'minimum_score' => (int) env('ASSISTANT_TOOL_INTENT_PROMPTS_MIN_SCORE', 1),

    /**
     * When the user sends one of these phrases (substring match, normalized like intents),
     * the sticky tool-flow is cleared and intent is re-evaluated for this message.
     */
    'flow_reset_phrases' => [
        'cambiar de tema',
        'otro tema',
        'cambiar de flujo',
        'volver al inicio',
        'empezar de nuevo',
        'no me hables de productos',
        'no es sobre productos',
        'hablame de otra cosa',
    ],

    /**
     * Tie-break order: first intent wins when two intents share the top score.
     */
    'intents_order' => [
        'chat_capabilities',
        'wapify',
        'collections_billing',
        'chat_commerce',
        'chat_calendar',
        'chat_tasks',
    ],

    /**
     * @var array<string, array{routing_keys: list<string>, phrases?: list<string>, words?: list<string>}>
     */
    'intents' => [

        'chat_capabilities' => [
            'routing_keys' => [
                'assistant:chat_capabilities',
                'chat_capabilities',
            ],
            'phrases' => [
                'quiero probar',
                'probar el asistente',
                'asistente de compra',
                'qué puedes hacer',
                'que puedes hacer',
                'qué podés hacer',
                'que podés hacer',
                'cómo funciona',
                'como funciona',
                'what can you do',
            ],
            'words' => [
                'demo',
                'tutorial',
                'recorrido',
                'capacidades',
                'funciones',
            ],
        ],

        'collections_billing' => [
            'routing_keys' => [
                'invoices:collections',
                'collections',
            ],
            'phrases' => [
                'recordatorio de pago',
                'recordatorio de factura',
                'factura impaga',
                'factura vencida',
                'factura pendiente',
                'cobranza amable',
                'mensaje de cobranza',
                'link de pago stripe',
                'portal del cliente',
                'portal de cliente stripe',
                'pago rechazado',
                'pago fallido',
                'tarjeta rechazada',
                'actualizar método de pago',
                'suscripción impaga',
                'cuota vencida',
            ],
            'words' => [
                'cobranzas',
                'cobranza',
                'morosidad',
                'moroso',
                'vencimiento',
                'stripe',
                'facturación',
                'facturacion',
            ],
        ],

        'wapify' => [
            'routing_keys' => [
                'products:wapify_me',
                'wapify_me',
            ],
            'phrases' => [
                'wapify.me',
                'wapify me',
                'wapify.me/launch',
                'wapify.me/demo',
                'pedimosfacil',
                'pedimos facil',
                'asistente de venta por whatsapp',
                'vender por whatsapp con wapify',
                'crear tu negocio wapify',
                'probar humano assistant',
                '7 días de prueba',
                'siete días de prueba',
                'promoción de lanzamiento',
                'promocion de lanzamiento',
            ],
            'words' => [
                'wapify',
            ],
        ],

        'chat_commerce' => [
            'routing_keys' => [
                'assistant:chat_commerce',
                'products:chat_commerce',
                'chat_commerce',
            ],
            'phrases' => [
                'quiero comprar',
                'agregar al carrito',
                'finalizar compra',
                'cerrar pedido',
                'quitar del carrito',
            ],
            'words' => [
                'catálogo',
                'catalogo',
                'productos',
                'carrito',
                'checkout',
                'finalizar',
                'pedido',
                'pagar',
                'agregar',
            ],
        ],

        'chat_calendar' => [
            'routing_keys' => [
                'assistant:chat_calendar',
                'chat_calendar',
            ],
            'phrases' => [
                'agendar cita',
                'reservar cita',
                'agendar una reunión',
                'hay disponibilidad',
            ],
            'words' => [
                'cita',
                'reunión',
                'reunion',
                'calendario',
                'agenda',
            ],
        ],

        'chat_tasks' => [
            'routing_keys' => [
                'assistant:chat_tasks',
                'tasks:chat_tasks',
                'chat_tasks',
            ],
            'phrases' => [
                'crear una tarea',
                'crear tarea',
                'nueva tarea',
            ],
            'words' => [
                'tarea',
                'recordatorio',
                'pendiente',
            ],
        ],
    ],
];
