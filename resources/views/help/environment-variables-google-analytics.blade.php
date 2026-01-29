@extends('layouts/layoutHelpSimple')

@section('title', __('Google Analytics (GA4)'))

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
                <h4 class="card-title mb-0">{{ __('Google Analytics (GA4)') }}</h4>
                <a href="{{ route('help.environment-variables') }}" class="btn btn-sm btn-label-secondary">{{ __('← Variables de Entorno') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Configuración de Google Analytics 4 por equipo para mostrar en el dashboard un gráfico de visitas y páginas vistas de los últimos 7 días.') }}</p>

                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-info-circle me-2"></i>{{ __('Dónde se configura') }}</h6>
                    <p class="mb-0">{{ __('Todo se configura en') }} <strong>{{ __('Configuración del equipo') }}</strong> (Team Settings) → <strong>{{ __('Google Analytics') }}</strong> → Configure. {{ __('No hace falta usar variables de entorno en el servidor.') }}</p>
                </div>

                <h5 class="mt-4">{{ __('1. Qué necesitas tener') }}</h5>
                <ul>
                    <li>{{ __('Una propiedad de Google Analytics 4 (GA4) con datos de tu sitio.') }}</li>
                    <li>{{ __('El') }} <strong>{{ __('ID de propiedad') }}</strong> {{ __('(Property ID): número numérico, no el ID de medición G- ni el ID del flujo.') }}</li>
                    <li>{{ __('Un archivo JSON de credenciales de una') }} <strong>{{ __('cuenta de servicio') }}</strong> {{ __('creada en Google Cloud, con la API de Google Analytics Data habilitada.') }}</li>
                    <li>{{ __('El email de esa cuenta de servicio añadido en GA4 con rol') }} <strong>{{ __('Analista') }}</strong>.
                    </li>
                </ul>

                <h5 class="mt-4">{{ __('2. Dónde encontrar el ID de propiedad correcto') }}</h5>
                <p>{{ __('En GA4 existen varios identificadores. Para Humano debes usar solo uno:') }}</p>
                <table class="table table-bordered mb-4">
                    <thead>
                        <tr>
                            <th>{{ __('Nombre en GA4') }}</th>
                            <th>{{ __('Formato') }}</th>
                            <th>{{ __('¿Usar en Humano?') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>{{ __('ID de medición') }}</strong> (Measurement ID)</td>
                            <td><code>G-XXXXXXXXXX</code></td>
                            <td><span class="badge bg-label-danger">{{ __('No') }}</span> {{ __('— Se usa en el código del sitio para enviar datos.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('ID del flujo') }}</strong> (Stream ID)</td>
                            <td>{{ __('Número (ej. 2396611762)') }}</td>
                            <td><span class="badge bg-label-danger">{{ __('No') }}</span> {{ __('— Es del flujo de datos, no de la propiedad.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('ID de propiedad') }}</strong> (Property ID)</td>
                            <td>{{ __('Número (ej. 267096786)') }}</td>
                            <td><span class="badge bg-label-success">{{ __('Sí') }}</span></td>
                        </tr>
                    </tbody>
                </table>
                <p><strong>{{ __('Cómo obtener el ID de propiedad:') }}</strong></p>
                <ol>
                    <li>{{ __('En') }} <a href="https://analytics.google.com" target="_blank" rel="noopener">analytics.google.com</a> {{ __('entra a tu propiedad.') }}</li>
                    <li>{{ __('Menú inferior izquierdo') }} → <strong>{{ __('Admin') }}</strong> (icono de engranaje).</li>
                    <li>{{ __('Columna central') }} → <strong>{{ __('Configuración de la propiedad') }}</strong> (Property settings).</li>
                    <li>{{ __('Arriba verás') }} <strong>{{ __('ID de propiedad') }}</strong>: {{ __('un número como') }} <code>267096786</code>. {{ __('Ese valor es el que debes pegar en Humano.') }}</li>
                </ol>

                <h5 class="mt-4">{{ __('3. Crear la cuenta de servicio y obtener el JSON') }}</h5>
                <p>{{ __('El JSON no se genera en Google Analytics; se crea en Google Cloud.') }}</p>
                <ol>
                    <li>
                        <strong>{{ __('Ir a Google Cloud') }}</strong><br>
                        <a href="https://console.cloud.google.com" target="_blank" rel="noopener">console.cloud.google.com</a> {{ __('(misma cuenta de Google que GA4).') }}
                    </li>
                    <li>
                        <strong>{{ __('Proyecto') }}</strong><br>
                        {{ __('Selecciona un proyecto existente o crea uno nuevo (nombre ej. "Humano Analytics").') }}
                    </li>
                    <li>
                        <strong>{{ __('Activar la API') }}</strong><br>
                        {{ __('Menú') }} → {{ __('API y servicios') }} → {{ __('Biblioteca') }} → buscar <strong>Google Analytics Data API</strong> → {{ __('Habilitar.') }}
                    </li>
                    <li>
                        <strong>{{ __('Crear cuenta de servicio') }}</strong><br>
                        {{ __('Menú') }} → {{ __('API y servicios') }} → {{ __('Credenciales') }} → {{ __('Crear credenciales') }} → {{ __('Cuenta de servicio') }}. {{ __('Nombre ej. "Humano Analytics"') }} → {{ __('Crear y continuar') }} → {{ __('Listo.') }}
                    </li>
                    <li>
                        <strong>{{ __('Crear clave JSON') }}</strong><br>
                        {{ __('En la tabla, entra en la cuenta de servicio recién creada') }} → {{ __('pestaña Claves') }} → {{ __('Añadir clave') }} → {{ __('Crear clave nueva') }} → tipo <strong>JSON</strong> → {{ __('Crear.') }} {{ __('Se descargará un archivo .json.') }}
                    </li>
                    <li>
                        <strong>{{ __('Pegar el JSON en Humano') }}</strong><br>
                        {{ __('Abre el archivo con un editor de texto, copia todo el contenido (desde') }} <code>{</code> {{ __('hasta') }} <code>}</code>) {{ __('y pégalo en Team Settings') }} → {{ __('Google Analytics') }} → {{ __('Service account credentials (JSON).') }}
                    </li>
                </ol>

                <h5 class="mt-4">{{ __('4. Dar acceso a la cuenta de servicio en GA4') }}</h5>
                <p>{{ __('Sin este paso, la API no podrá leer los datos de tu propiedad.') }}</p>
                <ol>
                    <li>{{ __('En GA4') }} → {{ __('Admin') }} → {{ __('Configuración de la propiedad') }} → {{ __('Acceso a la propiedad') }} (Property Access Management).</li>
                    <li>{{ __('Botón') }} <strong>+</strong> → {{ __('Añadir usuarios.') }}</li>
                    <li>{{ __('En "Dirección de correo electrónico" pega el email de la cuenta de servicio.') }} {{ __('Aparece en el JSON en') }} <code>"client_email"</code> {{ __('(ej.') }} <code>nombre@proyecto.iam.gserviceaccount.com</code>).</li>
                    <li>{{ __('En "Roles directos" selecciona') }} <strong>{{ __('Analista') }}</strong> (Analyst). {{ __('Con eso puede leer los datos; no hace falta Administrador ni Editor.') }}</li>
                    <li>{{ __('Pulsa Añadir.') }}</li>
                </ol>

                <h5 class="mt-4">{{ __('5. Completar la configuración en Humano') }}</h5>
                <ol>
                    <li>{{ __('Entra en') }} <strong>{{ __('Configuración del equipo') }}</strong> ({{ __('por ejemplo') }} <code>/team/&lt;id&gt;/settings</code>).</li>
                    <li>{{ __('Tarjeta') }} <strong>{{ __('Google Analytics') }}</strong> → {{ __('Configure.') }}</li>
                    <li>{{ __('GA4 Property ID: pega solo el número (ej.') }} <code>267096786</code>).</li>
                    <li>{{ __('Service account credentials (JSON): pega el contenido completo del archivo JSON descargado.') }}</li>
                    <li>{{ __('Guardar cambios.') }}</li>
                </ol>
                <p>{{ __('Si todo está correcto, al abrir el dashboard verás el bloque') }} <strong>{{ __('Google Analytics') }}</strong> {{ __('con un gráfico de visitas y páginas vistas de los últimos 7 días. Si no hay configuración válida, el bloque no se muestra.') }}</p>

                <h5 class="mt-4">{{ __('6. Resolución de problemas') }}</h5>
                <ul>
                    <li><strong>{{ __('No aparece el gráfico') }}</strong>: {{ __('Comprueba que usaste el ID de propiedad (Admin → Configuración de la propiedad), no el ID del flujo ni G-. Que el JSON esté completo y pegado sin cortes. Que el email de la cuenta de servicio esté en Acceso a la propiedad con rol Analista.') }}</li>
                    <li><strong>{{ __('Error de credenciales') }}</strong>: {{ __('Verifica que en Google Cloud esté habilitada la') }} <strong>Google Analytics Data API</strong> {{ __('y que el JSON corresponda al mismo proyecto.') }}</li>
                    <li><strong>{{ __('Sin datos en el gráfico') }}</strong>: {{ __('La propiedad debe tener tráfico reciente. El gráfico muestra los últimos 7 días; si no hay datos en ese periodo, las barras saldrán a cero.') }}</li>
                </ul>

                <hr class="my-4">
                <p class="text-muted small mb-0">
                    {{ __('Esta funcionalidad utiliza el paquete') }} <a href="https://github.com/spatie/laravel-analytics" target="_blank" rel="noopener">spatie/laravel-analytics</a> {{ __('y la') }} <a href="https://developers.google.com/analytics/devguides/reporting/data/v1" target="_blank" rel="noopener">Google Analytics Data API</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
