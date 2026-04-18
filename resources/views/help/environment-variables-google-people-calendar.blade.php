@extends('layouts/layoutHelpSimple')

@section('title', __('Google People y Calendar (sincronización OAuth)'))

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
                <h4 class="card-title mb-0">{{ __('Google People y Calendar (OAuth)') }}</h4>
                <a href="{{ route('help.environment-variables') }}" class="btn btn-sm btn-label-secondary">{{ __('← Variables de Entorno') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Permite que cada equipo conecte una cuenta de Google (usuario real) para sincronizar contactos y eventos del calendario hacia Humano. Es distinto de Google Analytics: allí se usa una cuenta de servicio; aquí se usa OAuth 2.0 con consentimiento del usuario.') }}</p>

                <div class="alert alert-warning mb-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-alert-triangle me-2"></i>{{ __('Importante') }}</h6>
                    <ul class="mb-0">
                        <li>{{ __('La conexión la hace un usuario autenticado; los datos se asocian al equipo actual (current team) y a ese usuario.') }}</li>
                        <li>{{ __('En producción hace falta que el servidor ejecute colas (queue worker) y el programador de Laravel (scheduler) para que la sincronización periódica funcione.') }}</li>
                    </ul>
                </div>

                <h5 class="mt-4">{{ __('1. Requisitos previos') }}</h5>
                <ul>
                    <li>{{ __('Proyecto en Google Cloud Console con las APIs habilitadas:') }} <strong>Google People API</strong> {{ __('y') }} <strong>Google Calendar API</strong>.</li>
                    <li>{{ __('Pantalla de consentimiento OAuth configurada (tipo interno o externo según tu organización).') }}</li>
                    <li>{{ __('Credenciales OAuth de tipo') }} <strong>{{ __('Aplicación web') }}</strong>: <code>Client ID</code> {{ __('y') }} <code>Client secret</code>.</li>
                    <li>{{ __('Migraciones de base de datos aplicadas en el entorno (tablas') }} <code>external_accounts</code>, <code>sync_cursors</code>, {{ __('etc.).') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('2. Variables de entorno en el servidor') }}</h5>
                <p>{{ __('Añade o revisa en el archivo') }} <code>.env</code> {{ __('del servidor (Forge, Herd local, etc.):') }}</p>
                <pre class="mb-3"><code class="language-env">APP_URL=https://humano.test
GOOGLE_CLIENT_ID=tu-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu-client-secret
GOOGLE_OAUTH_SCOPES="openid,email,profile,https://www.googleapis.com/auth/contacts,https://www.googleapis.com/auth/calendar.events"</code></pre>
                <ul class="small text-muted">
                    <li>{{ __('La URL de redirección OAuth es siempre') }} <code>{{ rtrim(config('app.url'), '/') }}/integrations/google/callback</code> {{ __('según') }} <code>APP_URL</code> {{ __('en') }} <code>config/app.php</code>. {{ __('Registrá esa URI exacta en Google Cloud.') }}</li>
                    <li><code>GOOGLE_OAUTH_SCOPES</code>: {{ __('lista separada por comas. Los valores por defecto incluyen permisos de lectura y escritura en contactos y eventos del calendario principal (primary), para importar desde Google y enviar cambios hechos en Humano hacia Google. Tras cambiar scopes, reconecta Google en Team Settings para que el usuario vuelva a aceptar el consentimiento.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('3. Google Cloud: URI de redirección') }}</h5>
                <p>{{ __('En Google Cloud Console → APIs y servicios → Credenciales → tu ID de cliente OAuth (aplicación web) → URI de redirección autorizadas, añade:') }}</p>
                <pre class="mb-3"><code>{{ rtrim(config('app.url'), '/') }}/integrations/google/callback</code></pre>
                <p class="small text-muted">{{ __('Para desarrollo local con Herd, algo como') }} <code>https://humano.test/integrations/google/callback</code> {{ __('si ese es tu') }} <code>APP_URL</code>.</p>

                <h5 class="mt-4">{{ __('4. Conectar la cuenta desde Humano') }}</h5>
                <ol>
                    <li>{{ __('Inicia sesión y selecciona el equipo que quieres configurar.') }}</li>
                    <li>{{ __('Ve a') }} <strong>{{ __('Configuración del equipo') }}</strong> (Team Settings): <code>{{ url('/team') }}/&lt;id&gt;/settings</code>.</li>
                    <li>{{ __('En la tarjeta') }} <strong>Google People &amp; Calendar</strong>, {{ __('pulsa') }} <strong>Connect</strong>.</li>
                    <li>{{ __('Completa el consentimiento de Google y acepta los permisos solicitados.') }}</li>
                    <li>{{ __('Al volver a la aplicación, la tarjeta debería mostrar') }} <strong>Connected</strong>. {{ __('Para revocar el acceso en Humano, usa') }} <strong>Disconnect</strong>.</li>
                </ol>
                <div class="alert alert-info">
                    <p class="mb-0">{{ __('La integración de Google Analytics (cuenta de servicio + Property ID) sigue configurándose en la misma zona de Team Settings, tarjeta') }} <strong>Google Analytics</strong>. {{ __('Son dos mecanismos distintos: uno no sustituye al otro.') }}</p>
                </div>

                <h5 class="mt-4">{{ __('5. Sincronización automática y manual') }}</h5>
                <p>{{ __('El programador de Laravel puede encolar el comando') }} <code>google:sync-data</code> {{ __('para todos los equipos con cuenta Google conectada. Asegúrate de tener el cron de Laravel:') }}</p>
                <pre class="mb-3"><code class="language-bash">* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1</code></pre>
                <p>{{ __('Y un worker de cola procesando la cola por defecto:') }}</p>
                <pre class="mb-3"><code class="language-bash">php artisan queue:work</code></pre>
                <p>{{ __('Sincronización manual desde servidor:') }}</p>
                <pre class="mb-3"><code class="language-bash">php artisan google:sync-data
php artisan google:sync-data --account_id=123</code></pre>

                <h5 class="mt-4">{{ __('6. Solución de problemas') }}</h5>
                <ul>
                    <li><strong>redirect_uri_mismatch</strong>: {{ __('la URI en Google Cloud no coincide con') }} <code>{{ rtrim(config('app.url'), '/') }}/integrations/google/callback</code> {{ __('(derivada de') }} <code>APP_URL</code>).</li>
                    <li><strong>access_denied</strong>: {{ __('el usuario canceló o la pantalla de consentimiento no incluye los scopes necesarios.') }}</li>
                    <li>{{ __('Sin datos nuevos: revisa que el worker de cola esté activo y que existan filas en') }} <code>sync_runs</code> {{ __('para diagnosticar errores de última ejecución.') }}</li>
                </ul>

                @auth
                    @if (auth()->user()->currentTeam)
                        <p class="mb-0">
                            <a href="{{ route('team-settings.index', auth()->user()->currentTeam) }}" class="btn btn-sm btn-primary">{{ __('Ir a Team Settings') }}</a>
                        </p>
                    @else
                        <p class="mb-0 text-muted">{{ __('Selecciona un equipo en la aplicación y abre Configuración del equipo desde el menú de usuario.') }}</p>
                    @endif
                @else
                    <p class="mb-0 text-muted">{{ __('Inicia sesión en Humano y abre Configuración del equipo (Team Settings) desde el menú de usuario.') }}</p>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
