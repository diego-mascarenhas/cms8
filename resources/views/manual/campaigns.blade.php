@extends('layouts/layoutManual')

@section('title', __('Mensajes y plantillas'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Mensajes y plantillas') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Mensajes (campañas)') }}</h5>
                <p>{{ __('Los mensajes son campañas de email o SMS que envías a tus contactos. Puedes:') }}</p>
                <ul>
                    <li>{{ __('Crear un mensaje y elegir el público (por ejemplo todos los contactos, una lista, un filtro).') }}</li>
                    <li>{{ __('Redactar el contenido del mensaje o basarte en una plantilla.') }}</li>
                    <li>{{ __('Programar o lanzar la campaña, pausarla y reanudarla si hace falta.') }}</li>
                    <li>{{ __('Enviar una prueba a tu propio email o teléfono antes de lanzar.') }}</li>
                    <li>{{ __('Seguir envíos, aperturas o clics en enlaces si la plataforma lo registra.') }}</li>
                    <li>{{ __('Reenviar un envío concreto si falló o el destinatario no lo recibió.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Plantillas') }}</h5>
                <p>{{ __('Las plantillas son diseños reutilizables para tus mensajes (cabecera, pie, bloques de texto, botones, etc.). Puedes crear y editar plantillas (por ejemplo en HTML o con el editor visual) y usarlas en las campañas para no empezar de cero cada vez y mantener una imagen coherente.') }}</p>

                <p class="mb-0">{{ __('Con mensajes y plantillas puedes hacer newsletters, recordatorios, notificaciones o cualquier comunicación masiva desde Humano.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
