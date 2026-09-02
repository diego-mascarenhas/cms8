<?php

return [
    'page_title' => 'Tarifas de consumo — Ayuda',
    'title' => 'Facturación de consumo por equipo',
    'sidebar_title' => 'Tarifas de consumo',
    'index_card_title' => 'Tarifas de consumo',
    'index_card_body' => 'Preview de factura, tarifas SCD2, frecuencia mensual o semanal, y desglose de tokens por módulo. Solo root.',
    'intro' => 'El consumo de tokens, WhatsApp y email de excedente se factura aparte de la cuota del plan. Cada equipo puede tener tarifas propias y frecuencia mensual o semanal. La página de Tarifas muestra un preview de lo que se debería facturar; Stripe aún no emite esas facturas.',

    'where_heading' => 'Dónde está',
    'where_body' => 'Solo el rol root ve esta pantalla. En Gestión de Cuentas, el icono de euro de cada fila abre las tarifas de ese equipo.',
    'where_path' => 'Cuentas → icono euro → Tarifas',
    'where_route' => '/account-management/{id}/rates',

    'two_invoices_heading' => 'Dos facturas distintas',
    'two_invoices_plan' => 'Cuota del plan: Assistant, Business u otro producto contratado. Sigue saliendo en las facturas de Stripe de suscripción.',
    'two_invoices_usage' => 'Consumo: tokens IA, envíos WhatsApp y emails de excedente. Va en una factura de uso por equipo y periodo. Todavía no se emite en Stripe.',

    'rates_heading' => 'Tarifas',
    'rates_intro' => 'Hay tres productos. Si el equipo no tiene tarifa propia, se usa la de plataforma y, si tampoco existe, la de configuración.',
    'rates_tokens' => 'Multiplicador de tokens: el cliente ve N × tokens reales a tarifa OpenRouter, sin recargo extra. Por defecto ×10.',
    'rates_whatsapp' => 'Envío WhatsApp: EUR por mensaje saliente. Por defecto 0,003 EUR.',
    'rates_mailer' => 'Envío mail: EUR por email de excedente (por encima del tope mensual del plan). Por defecto 0,01 EUR.',
    'rates_history' => 'Al guardar una tarifa nueva, la anterior se conserva (SCD2) para el consumo ya ocurrido. El historial de la página muestra Desde / Hasta / Actual.',

    'frequency_heading' => 'Frecuencia',
    'frequency_intro' => 'Mensual o semanal, por equipo. Sin ancla: el mes va del 1 al 1 y la semana de lunes a lunes. Al cambiar la frecuencia, ese día queda como ancla.',
    'frequency_weekly' => 'Semanal: ventanas de 7 días desde el día del cambio (miércoles a miércoles si cambias un miércoles).',
    'frequency_monthly' => 'Mensual: del día D al D (del 15 al 15 si cambias un día 15).',
    'frequency_anchor' => 'Si el ancla es 29, 30 o 31 y el mes no tiene ese día, se usa el último día del mes. Al mes siguiente se recupera el ancla (31 ene → 28/29 feb → 31 mar → 30 abr → 31 may).',

    'change_heading' => 'Al cambiar la frecuencia',
    'change_intro' => 'Si solo cambias importes y dejas la misma frecuencia, se guarda sin aviso. Si cambias Mensual ↔ Semanal, aparece el aviso ¿Cambiar facturación? con los ítems y el total.',
    'change_close' => 'Se cierra el ciclo en curso hasta las 00:00 del día del cambio. Ese tramo queda como factura de ajuste.',
    'change_open' => 'El día del cambio entra en el ciclo nuevo. La nueva modalidad arranca ese día a las 00:00.',
    'change_stripe' => 'Confirmar cierra el ciclo en Humano. Aún no se emite nada en Stripe.',

    'items_heading' => 'Qué se imprime en la factura',
    'items_intro' => 'Cada documento (ajuste o ciclo abierto) lleva las mismas tres líneas, aunque el importe sea 0,00 EUR:',
    'items_tokens' => 'Tokens IA · periodo: tokens facturados (reales × multiplicador) e importe.',
    'items_sources' => 'Debajo, el desglose por módulo cuando hay origen: Chat, Projects, Insights u otros. Si todo el consumo va sin módulo, solo se muestra el total.',
    'items_whatsapp' => 'Envíos WhatsApp · periodo: número de envíos e importe.',
    'items_mailer' => 'Emails de excedente · periodo: emails por encima del tope e importe.',
    'items_total' => 'El preview de la página y el modal de confirmación listan esos ítems. El KPI Tokens muestra el total del preview (ciclo abierto más ajustes pendientes).',

    'preview_heading' => 'Preview',
    'preview_kpis' => 'A facturar, coste OpenRouter, markup (diferencia por el multiplicador) y tokens totales.',
    'preview_table' => 'Debajo, una tabla por factura: título, periodo, tokens del tramo, líneas y total. El pie es el total pendiente.',
    'preview_months' => 'Meses anteriores usa meses de calendario, no ciclos 15–15 o mié–mié.',

    'status_heading' => 'Estado actual',
    'status_not_issued' => 'El consumo se calcula y se muestra. No se crea invoice de Stripe de uso.',
    'status_adjustments' => 'Al cambiar la frecuencia se guarda un ajuste pendiente (invoiced_at vacío). El preview lo incluye si ese tramo tuvo consumo.',
    'status_weeks' => 'Las semanas ya cerradas mientras el equipo sigue en semanal no se encolan solas. El consumo queda en la base y se verá al facturar o al cambiar de modalidad.',
    'status_mailer' => 'El tope de email se aplica a cada trozo del periodo. Un mes partido puede marcar 0 excedente aunque el mes entero lo superaría.',

    'cli_heading' => 'Línea de comandos',
    'cli_body' => 'Para fijar una tarifa sin pasar por la pantalla (team_id 0 = default de plataforma):',
    'cli_example' => 'php artisan billing:set-team-rate {team_id} {product} {amount}',
    'cli_products' => 'product: tokens_multiplier, whatsapp_send o mailer_send. Opcional: --from= y --currency=.',

    'related_heading' => 'Relacionado',
    'related_stripe' => 'Webhooks de Stripe (cuota del plan y facturas de suscripción)',
    'related_manual' => 'Manual: facturas y pagos de clientes (CRM), distinto de este consumo de plataforma',
];
