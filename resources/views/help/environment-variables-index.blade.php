@extends('layouts/layoutHelpSimple')

@section('title', __('Variables de Entorno'))

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
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Variables de Entorno y Configuraciones') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('En esta sección se documentan todas las configuraciones que puedes definir por equipo en Humano, tanto en Team Settings como variables de entorno o integraciones externas.') }}</p>

                <h5 class="mt-4">{{ __('Dónde configurar') }}</h5>
                <p>{{ __('La mayoría de configuraciones por equipo se gestionan en') }} <strong>{{ __('Configuración del equipo') }}</strong> (Team Settings): {{ __('accede desde el menú de usuario o desde') }} <code>{{ url('/team') }}/&lt;id&gt;/settings</code>. {{ __('Cada equipo puede tener sus propias credenciales e IDs.') }}</p>

                <h5 class="mt-4">{{ __('Configuraciones documentadas') }}</h5>
                <div class="row mt-3">
                    <div class="col-md-6 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <i class="ti ti-chart-line me-2 text-primary"></i>
                                    {{ __('Google Analytics (GA4)') }}
                                </h6>
                                <p class="card-text small mb-2">{{ __('Mostrar visitas y páginas vistas en el dashboard. Property ID, cuenta de servicio y permisos en GA4.') }}</p>
                                <a href="{{ route('help.environment-variables.google-analytics') }}" class="btn btn-sm btn-primary">{{ __('Ver instructivo') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <i class="ti ti-calendar-event me-2 text-primary"></i>
                                    {{ __('Google People y Calendar (OAuth)') }}
                                </h6>
                                <p class="card-text small mb-2">{{ __('Sincronizar contactos y eventos del calendario de una cuenta Google por equipo. Credenciales OAuth en el servidor y conexión desde Team Settings.') }}</p>
                                <a href="{{ route('help.environment-variables.google-people-calendar') }}" class="btn btn-sm btn-primary">{{ __('Ver instructivo') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <i class="ti ti-brand-wordpress me-2 text-success"></i>
                                    {{ __('WooCommerce') }}
                                </h6>
                                <p class="card-text small mb-2">{{ __('Conectar tu tienda WooCommerce para gestionar productos y pedidos desde Humano. URL, Consumer Key y Consumer Secret.') }}</p>
                                <a href="{{ route('help.woocommerce-configuration') }}" class="btn btn-sm btn-success">{{ __('Ver instructivo') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border h-100 opacity-75">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <i class="ti ti-brand-stripe me-2 text-muted"></i>
                                    {{ __('Stripe') }}
                                    <span class="badge bg-label-secondary ms-2">{{ __('Próximamente') }}</span>
                                </h6>
                                <p class="card-text small mb-2 text-muted">{{ __('Claves API y webhook para pagos con Stripe.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border h-100 opacity-75">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <i class="ti ti-mail me-2 text-muted"></i>
                                    {{ __('Email (SMTP / IMAP)') }}
                                    <span class="badge bg-label-secondary ms-2">{{ __('Próximamente') }}</span>
                                </h6>
                                <p class="card-text small mb-2 text-muted">{{ __('Configuración de correo saliente e incoming para el equipo.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border h-100 opacity-75">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <i class="ti ti-phone me-2 text-muted"></i>
                                    {{ __('Twilio') }}
                                    <span class="badge bg-label-secondary ms-2">{{ __('Próximamente') }}</span>
                                </h6>
                                <p class="card-text small mb-2 text-muted">{{ __('SMS y WhatsApp: Account SID, token y números.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
