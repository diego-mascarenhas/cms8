@extends('layouts/layoutManual')

@section('title', __('Guía rápida: venta por WhatsApp'))

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 col-xl-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Guía rápida de Wapify') }}</h4>
                <p class="text-muted small mb-0 mt-1">{{ __('Paso a paso para configurar tu negocio, vender por WhatsApp y gestionar pedidos en Wapify.') }}</p>
            </div>
            <div class="card-body">
                <ol class="ps-3 mb-0">
                    <li id="configuracion-negocio" class="mb-4">
                        <h6 class="mb-2">{{ __('1. Configuración del negocio') }}</h6>
                        <p class="mb-2">{{ __('Ingresá a Wapify y completá los datos de tu negocio: nombre comercial, horarios de atención, zona de entrega y medios de pago. Esta base permite que el asistente responda con información correcta a cada cliente.') }}</p>
                        @auth
                            <p class="mb-0"><a href="{{ url('/dashboard') }}" class="fw-medium">{{ __('Ir al panel') }}</a></p>
                        @else
                            <p class="mb-0"><a href="{{ route('login') }}" class="fw-medium">{{ __('Iniciar sesión') }}</a></p>
                        @endauth
                    </li>

                    <li id="carga-productos" class="mb-4">
                        <h6 class="mb-2">{{ __('2. Carga de productos') }}</h6>
                        <p class="mb-2">{!! __('Entrá a <strong>Productos</strong> y cargá tu catálogo con nombre, precio, descripción y disponibilidad. Usá fotos claras y categorías para que el cliente encuentre rápido lo que busca cuando consulta por WhatsApp.') !!}</p>
                        @auth
                            @can('viewAny', \App\Models\Product::class)
                                <p class="mb-0"><a href="{{ route('product.index') }}" class="fw-medium">{{ __('Ir a Productos') }}</a></p>
                            @else
                                <p class="mb-0 text-muted small">{{ __('Necesitás permiso para ver productos en tu rol.') }}</p>
                            @endcan
                        @else
                            <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/product/list</code></p>
                        @endauth
                    </li>

                    <li id="escaneo-qr" class="mb-4">
                        <h6 class="mb-2">{{ __('3. Escaneo de QR') }}</h6>
                        <p class="mb-2">{!! __('Abrí <strong>Chat</strong> y vinculá tu número de WhatsApp escaneando el código QR desde el celular (Dispositivos vinculados / Emparejar dispositivo). Una vez conectado, Wapify puede recibir y responder mensajes en tiempo real.') !!}</p>
                        @auth
                            <p class="mb-0"><a href="{{ route('chat.index') }}" class="fw-medium">{{ __('Abrir Chat y WhatsApp') }}</a></p>
                        @else
                            <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/chat</code></p>
                        @endauth
                    </li>

                    <li id="pedidos" class="mb-4">
                        <h6 class="mb-2">{{ __('4. Pedidos') }}</h6>
                        <p class="mb-2">{!! __('Cada compra que llega por WhatsApp aparece en <strong>Pedidos</strong> con detalle de productos, cliente, dirección y total. Confirmá disponibilidad, tiempos de entrega y forma de pago para avanzar con claridad.') !!}</p>
                        @auth
                            @can('viewAny', \App\Models\Order::class)
                                <p class="mb-0"><a href="{{ route('order.index') }}" class="fw-medium">{{ __('Ir a Pedidos') }}</a></p>
                            @else
                                <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/order/list</code></p>
                            @endcan
                        @else
                            <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/order/list</code></p>
                        @endauth
                    </li>

                    <li id="ordenes" class="mb-0">
                        <h6 class="mb-2">{{ __('5. Ordenes') }}</h6>
                        <p class="mb-2">{!! __('Gestioná las ordenes por estado: nueva, en preparación, en camino y entregada. Mantener cada orden actualizada ayuda al equipo a trabajar coordinado y al cliente a saber exactamente en qué etapa está su compra.') !!}</p>
                        @auth
                            @can('viewAny', \App\Models\Order::class)
                                <p class="mb-0"><a href="{{ route('order.index') }}" class="fw-medium">{{ __('Ver ordenes') }}</a></p>
                            @else
                                <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/order/list</code></p>
                            @endcan
                        @else
                            <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/order/list</code></p>
                        @endauth
                    </li>
                </ol>

                <hr class="my-4">

                <p class="text-muted small mb-0">
                    {{ __('Tip: seguí este orden para empezar rápido en Wapify:') }}
                    {{ __('configuración del negocio -> productos -> QR -> pedidos -> ordenes.') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
