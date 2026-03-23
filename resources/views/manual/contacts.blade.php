@extends('layouts/layoutManual')

@section('title', __('Contactos'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Contactos') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Lista de contactos') }}</h5>
                <p>{{ __('Los contactos son las personas con las que interactúas: leads, prospectos o clientes. Desde la lista de contactos puedes:') }}</p>
                <ul>
                    <li>{{ __('Ver todos los contactos en una tabla con búsqueda y filtros.') }}</li>
                    <li>{{ __('Crear contactos nuevos (nombre, email, teléfono, empresa, etc.) y editar los existentes.') }}</li>
                    <li>{{ __('Importar contactos desde un archivo (CSV o Excel): subes el archivo, asignas columnas a cada campo y se crean o actualizan los contactos en bloque.') }}</li>
                    <li>{{ __('Vincular un contactos a una cuenta de usuario para que pueda iniciar sesión en la plataforma.') }}</li>
                    <li>{{ __('Asociar el contacto a una empresa (enterprise) para tener claro a qué compañía pertenece.') }}</li>
                    <li>{{ __('Registrar sentimiento (positivo, neutro, negativo) o datos personalizados según la configuración de tu equipo.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Prospección / Buscar clientes') }}</h5>
                <p>{{ __('Desde la función de prospección puedes buscar personas y empresas en fuentes externas (por ejemplo Apollo) y añadirlas como contactos. Sirve para ampliar tu base de contactos con datos de calidad sin tener que introducirlos a mano.') }}</p>

                <h5 class="mt-4">{{ __('Lista de 60') }}</h5>
                <p>{{ __('La Lista de 60 es una lista prioritaria (por ejemplo los 60 contactos más importantes a los que hacer seguimiento). Puedes añadir o quitar contactos de esta lista para concentrarte en quienes más importan y no perder el hilo con nadie.') }}</p>

                <p class="mb-0">{{ __('Los contactos se utilizan en proyectos, mensajes, chat y otros módulos; son la base de tu CRM en Humano.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
