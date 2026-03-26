@extends('layouts/layoutManual')

@section('title', __('Guía rápida: venta por WhatsApp'))

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 col-xl-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Guía rápida: venta por WhatsApp') }}</h4>
                <p class="text-muted small mb-0 mt-1">{{ __('Del inicio de sesión a cobrar pedidos y coordinar entregas con el asistente.') }}</p>
            </div>
            <div class="card-body">
                <ol class="ps-3 mb-0">
                    <li class="mb-4">
                        <h6 class="mb-2">{{ __('1. Iniciá sesión y elegí tu equipo') }}</h6>
                        <p class="mb-2">{{ __('Entrá con tu usuario y contraseña. Si tenés varios equipos, seleccioná el correcto en el selector de equipo (arriba o en tu perfil), porque productos, pedidos y WhatsApp son por equipo.') }}</p>
                        @auth
                            <p class="mb-0"><a href="{{ url('/dashboard') }}" class="fw-medium">{{ __('Ir al panel') }}</a></p>
                        @else
                            <p class="mb-0"><a href="{{ route('login') }}" class="fw-medium">{{ __('Iniciar sesión') }}</a></p>
                        @endauth
                    </li>

                    <li class="mb-4">
                        <h6 class="mb-2">{{ __('2. Conectá WhatsApp y escaneá el QR') }}</h6>
                        <p class="mb-2">{!! __('Abrí <strong>Chat</strong> en el menú. Ahí vinculás el número de WhatsApp del negocio: mostramos un código QR; escanealo con WhatsApp en el teléfono (Dispositivos vinculados / Emparejar). Cuando quede conectado, ya podés recibir y enviar mensajes desde Humano.') !!}</p>
                        @auth
                            <p class="mb-0"><a href="{{ route('chat.index') }}" class="fw-medium">{{ __('Abrir Chat y WhatsApp') }}</a></p>
                        @else
                            <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/chat</code></p>
                        @endauth
                    </li>

                    <li class="mb-4">
                        <h6 class="mb-2">{{ __('3. Cargá o editá tus productos para vender') }}</h6>
                        <p class="mb-2">{!! __('En <strong>Productos</strong> revisá el catálogo: nombre, precio, descripción, sucursal si aplica, y activá los que quieras ofrecer por WhatsApp. Los clientes usan ese catálogo cuando compran con el asistente o el flujo de carrito.') !!}</p>
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

                    <li class="mb-4">
                        <h6 class="mb-2">{{ __('4. Recibí pedidos y gestioná el estado') }}</h6>
                        <p class="mb-2">{!! __('Los pedidos que entran por WhatsApp o los que cargás a mano aparecen en <strong>Pedidos</strong>. Revisá cada uno: líneas, cliente, total y estado (pendiente, en preparación, enviado, etc.). Actualizá el estado para que tu equipo y el cliente vean el avance.') !!}</p>
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

                    <li class="mb-0">
                        <h6 class="mb-2">{{ __('5. Usá el asistente para responder y coordinar entregas') }}</h6>
                        <p class="mb-2">{!! __('En la misma pantalla de <strong>Chat</strong> tenés el asistente: puede ayudarte a contestar consultas, seguir pedidos y dar información de envío o retiro según cómo lo tengas configurado. Revisá las respuestas antes de enviar si tu equipo trabaja con revisión manual.') !!}</p>
                        @auth
                            <p class="mb-0"><a href="{{ route('chat.index') }}" class="fw-medium">{{ __('Abrir Chat / asistente') }}</a></p>
                        @else
                            <p class="mb-0 text-muted small">{{ __('Ruta:') }} <code class="user-select-all">/chat</code></p>
                        @endauth
                    </li>
                </ol>

                <hr class="my-4">

                <p class="text-muted small mb-0">
                    {{ __('Más detalle en el manual:') }}
                    <a href="{{ route('manual.chat') }}">{{ __('Chat y WhatsApp') }}</a>,
                    <a href="{{ route('manual.products-and-orders') }}">{{ __('Productos y pedidos') }}</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
