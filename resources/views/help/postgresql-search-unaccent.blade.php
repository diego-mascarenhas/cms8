@extends('layouts/layoutHelpSimple')

@section('title', __('PostgreSQL: búsqueda y extensión unaccent'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('PostgreSQL: búsqueda insensible a acentos (unaccent)') }}</h4>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('help.environment-variables') }}" class="btn btn-sm btn-label-secondary">{{ __('← Variables de Entorno') }}</a>
                    <a href="{{ route('help.index') }}" class="btn btn-sm btn-label-secondary">{{ __('← Ayuda') }}</a>
                </div>
            </div>
            <div class="card-body">
                <p class="lead mb-3">
                    {{ __('En Humano, las búsquedas globales (navbar, listas DataTables de contactos y clientes, etc.) pasan por la clase') }}
                    <code>App\Support\SearchNormalizer</code>.
                    {{ __('En PostgreSQL, si está disponible la extensión') }} <code>unaccent</code>,
                    {{ __('las coincidencias usan') }} <code>lower(unaccent(...))</code>
                    {{ __('para alinear mejor consultas sin acentos con texto almacenado con acentos.') }}
                </p>

                <div class="alert alert-info mb-4" role="alert">
                    <h6 class="alert-heading mb-2"><i class="ti ti-info-circle me-2"></i>{{ __('Sin unaccent') }}</h6>
                    <p class="mb-0">{{ __('Si la extensión no está instalada en esa base de datos, el código usa una ruta alternativa (normalización en PHP y comparaciones SQL con sustitución de caracteres / LIKE). La aplicación sigue funcionando; solo cambia la estrategia en el servidor de base de datos.') }}</p>
                </div>

                <h5 class="mt-4">{{ __('1. Comprobar si unaccent está activa') }}</h5>
                <p>{{ __('Conéctate a la misma base de datos que usa Laravel (') }}<code>DB_DATABASE</code>{{ __(') y ejecuta:') }}</p>
                <pre class="language-sql mb-3"><code class="language-sql">SELECT extname FROM pg_extension WHERE extname = 'unaccent';</code></pre>
                <p class="mb-0">{{ __('Si el resultado está vacío, la extensión no está creada en esa base de datos.') }}</p>

                <h5 class="mt-4">{{ __('2. Activar unaccent en la base de datos') }}</h5>
                <p>{{ __('Ejecuta (requiere permisos suficientes, normalmente superusuario o rol con derecho a crear extensiones):') }}</p>
                <pre class="language-sql mb-3"><code class="language-sql">CREATE EXTENSION IF NOT EXISTS unaccent;</code></pre>
                <ul>
                    <li>{{ __('Esto se guarda en el catálogo de PostgreSQL: permanece tras reiniciar el servidor Postgres o la máquina.') }}</li>
                    <li>{{ __('Cada base de datos tiene sus propias extensiones: si tienes varias BDs, repite el comando en la que usa la aplicación.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('3. Ubuntu / paquetes en el servidor') }}</h5>
                <p>{{ __('CREATE EXTENSION no descarga paquetes del sistema operativo; usa los ficheros que ya trae la instalación de PostgreSQL (habitualmente el paquete') }} <code>postgresql-contrib</code> {{ __('o equivalente según versión).') }}</p>
                <p>{{ __('Si al crear la extensión aparece un error indicando que falta el fichero de control o que la extensión no está disponible, instala contrib en el servidor, por ejemplo:') }}</p>
                <pre class="language-bash mb-3"><code class="language-bash">sudo apt install postgresql-contrib</code></pre>
                <p class="text-muted small mb-0">{{ __('El nombre exacto del paquete puede incluir la versión mayor de Postgres (p. ej. postgresql-16 en algunas distribuciones).') }}</p>

                <h5 class="mt-4">{{ __('4. Tras habilitar unaccent') }}</h5>
                <p>{{ __('La aplicación cachea en memoria (por conexión) si unaccent está disponible. Después de ejecutar CREATE EXTENSION en producción, conviene reiniciar workers PHP (Forge, Supervisor, Horizon, contenedor, etc.) o el servicio web para que los procesos vuelvan a detectar la extensión sin usar una respuesta cacheada antigua.') }}</p>

                <h5 class="mt-4">{{ __('5. MySQL y SQLite') }}</h5>
                <p class="mb-0">{{ __('No se usa la extensión unaccent de PostgreSQL. SearchNormalizer sigue aplicando normalización del término de búsqueda y condiciones SQL adaptadas al motor (por ejemplo cadena de replace en MySQL).') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
