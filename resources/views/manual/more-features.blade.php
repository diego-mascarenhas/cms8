@extends('layouts/layoutManual')

@section('title', __('Más funciones'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Más funciones') }}</h4>
            </div>
            <div class="card-body">
                <p>{{ __('Humano incluye otros módulos que pueden estar disponibles según tu plan y configuración. Resumen breve:') }}</p>

                <h5>{{ __('Empresas (Enterprises)') }}</h5>
                <p>{{ __('Las empresas son las compañías u organizaciones con las que trabajas. Puedes mantener un listado de empresas y asociar contactos o clientes a cada una para tener claro la relación entre personas y empresas.') }}</p>

                <h5 class="mt-4">{{ __('Contenidos (Contents)') }}</h5>
                <p>{{ __('Los contenidos son bloques de texto o contenido reutilizable (para web, emails, documentos). Puedes crear, ordenar y editar contenidos para usarlos en varias partes de la plataforma o en comunicaciones.') }}</p>

                <h5 class="mt-4">{{ __('Prompts') }}</h5>
                <p>{{ __('Los prompts son instrucciones predefinidas para el asistente con IA. Puedes crear y gestionar prompts para que el asistente responda o se comporte de forma coherente (tono, temas, límites).') }}</p>

                <h5 class="mt-4">{{ __('Notificaciones') }}</h5>
                <p>{{ __('Puedes crear y enviar notificaciones (en la app o por email) a usuarios o contactos, de forma individual o masiva, para avisos, recordatorios o comunicaciones internas.') }}</p>

                <h5 class="mt-4">{{ __('Documentación, multimedia y web') }}</h5>
                <p>{{ __('El módulo de documentación lista archivos o documentos del equipo. Multimedia es una galería de imágenes o archivos. Si tu sitio web está conectado, puedes tener gestión de entradas y páginas (por ejemplo WordPress) o un editor de landing pages para crear páginas de aterrizaje.') }}</p>

                <h5 class="mt-4">{{ __('Embudo, automatizaciones e integraciones') }}</h5>
                <p>{{ __('El embudo (funnel) ayuda a diseñar el flujo de ventas o de proceso. Las automatizaciones ejecutan acciones cuando se cumplen ciertas condiciones (por ejemplo enviar un email cuando un contacto pasa a “cliente”). Las integraciones conectan Humano con herramientas externas (CRM, email, etc.).') }}</p>

                <h5 class="mt-4">{{ __('Infraestructura (servidores, hosting)') }}</h5>
                <p>{{ __('Si gestionas infraestructura, puedes dar de alta servidores y hosting y comprobar su estado o conexión desde la plataforma.') }}</p>

                <h5 class="mt-4">{{ __('Suscripción y facturación de Humano') }}</h5>
                <p>{{ __('Desde la sección de suscripción o facturación puedes gestionar tu plan de Humano, datos de pago y facturación de la plataforma.') }}</p>

                <p class="mb-0">{{ __('Para configuración técnica (API, variables de entorno, WooCommerce, etc.) consulta') }} <a href="{{ route('help.index') }}">{{ __('Ayuda y documentación') }}</a>.</p>
            </div>
        </div>
    </div>
</div>
@endsection
