<?php

namespace App\Support;

class HumanoGuidePresentations
{
    /**
     * @return list<array{url: string, title: string, subtitle: string, description: string, icon: string}>
     */
    public static function all(): array
    {
        return [
            [
                'url' => GuidePresentation::url('primeros-pasos'),
                'title' => __('Primeros pasos'),
                'subtitle' => __('Cómo funciona Humano'),
                'description' => __('Configuración del negocio en seis pasos: marca, contacto, desafío e informe.'),
                'icon' => 'settings',
            ],
            [
                'url' => GuidePresentation::url('chat-contactos-modulos'),
                'title' => __('Chat, contactos y módulos'),
                'subtitle' => __('El día a día en el panel'),
                'description' => __('Conversaciones, agenda de contactos y herramientas según tu plan.'),
                'icon' => 'messages',
            ],
            [
                'url' => GuidePresentation::url('calendario'),
                'title' => __('Calendario'),
                'subtitle' => __('Agenda y eventos'),
                'description' => __('Vista mensual y semanal, citas con clientes y recordatorios del equipo.'),
                'icon' => 'calendar',
            ],
            [
                'url' => GuidePresentation::url('tareas'),
                'title' => __('Tareas'),
                'subtitle' => __('Pendientes del equipo'),
                'description' => __('Lista y tablero por estado, responsables, fechas y vínculo con contactos.'),
                'icon' => 'layout-kanban',
            ],
            [
                'url' => GuidePresentation::url('prospeccion'),
                'title' => __('Prospección'),
                'subtitle' => __('Buscar contactos'),
                'description' => __('Busca perfiles por cargo y ubicación e impórtalos a tu agenda con créditos de prospectos.'),
                'icon' => 'target',
            ],
            [
                'url' => GuidePresentation::url('facturacion'),
                'title' => __('Facturación'),
                'subtitle' => __('Cobros y contabilidad'),
                'description' => __('Sube una foto de factura o pago por asistente, email o WhatsApp y regístrala en tu sistema contable al instante.'),
                'icon' => 'receipt',
            ],
            [
                'url' => GuidePresentation::url('afiliados'),
                'title' => __('Afiliados'),
                'subtitle' => __('Programa de referidos'),
                'description' => __('Comparte tu enlace o invita por email: cobras comisión por cada suscripción que llegue con tu código de referido.'),
                'icon' => 'affiliate',
            ],
        ];
    }
}
