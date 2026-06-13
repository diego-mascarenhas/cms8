@extends('layouts/layoutMaster')

@section('title', 'Team Settings')

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    .team-settings-card--disabled {
        position: relative;
        overflow: hidden;
    }

    .team-settings-card--disabled::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(128, 128, 128, 0.35);
        border-radius: inherit;
        pointer-events: none;
        z-index: 1;
    }

    .team-settings-card--disabled .card-body {
        opacity: 0.75;
    }
</style>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Team Settings</h4>
        <p class="text-muted">Configure your team settings and preferences</p>
    </div>
</div>

    <div class="row">
        <div class="col-md-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning">
                    {{ session('warning') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('webdav_credentials'))
                @php($credentials = session('webdav_credentials'))
                <div class="alert alert-info">
                    <strong>{{ __('app.webdav_credentials_title') }}</strong>
                    <ul class="mb-0 mt-2">
                        <li>{{ __('app.webdav_server_url') }}: <code>{{ $credentials['dav_url'] ?? '' }}</code></li>
                        <li>{{ __('app.webdav_username') }}: <code>{{ $credentials['email'] ?? '' }}</code></li>
                        @if (! empty($credentials['password']))
                            <li>{{ __('Password') }}: <code>{{ $credentials['password'] }}</code></li>
                        @endif
                        <li>{{ __('app.webdav_principal') }}: <code>{{ $credentials['principal'] ?? '' }}</code></li>
                    </ul>
                </div>
            @endif

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-brand-stripe mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Stripe Integration</h5>
                            <p class="card-text">Configure Stripe API keys and webhook settings</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'stripe']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-file-export mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Exportación fiscal') }}</h5>
                            <p class="card-text">{{ __('Plataforma fiscal, país y enrutado automático de facturas locales') }}</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'fiscal']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-file-invoice mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Cuéntica') }}</h5>
                            <p class="card-text">{{ __('Credenciales API para exportar facturas a Cuéntica (España)') }}</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'cuentica']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-category mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Categories</h5>
                            <p class="card-text">Configure default category settings and preferences</p>
                            <div class="btn-group">
                                <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'categories']) }}" class="btn btn-primary">Configure</a>
                                <a href="{{ route('categories.index') }}" class="btn btn-outline-primary">Manage</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-bell mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Notifications</h5>
                            <p class="card-text">Manage notification preferences for your team</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'notifications']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div @class([
                        'card',
                        'h-100',
                        'team-settings-card--disabled' => ! $performanceInsightsEnabled,
                    ]) data-module="performance_insights">
                        <div class="card-body text-center">
                            <i class="ti ti-{{ $performanceInsightsModule?->icon ?? 'chart-infographic' }} mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ $performanceInsightsModule?->name ?? __('app.performance_insights_menu') }}</h5>
                            <p class="card-text">{{ $performanceInsightsModule?->description ?? __('app.shortcuts.performance_insights') }}</p>
                            @if ($performanceInsightsEnabled)
                                <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'notifications']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                            @else
                                <span class="badge bg-label-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-star mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Valorations</h5>
                            <p class="card-text">Manage contact valorations for your team</p>
                            <a href="{{ route('team-settings.valorations', $team) }}" class="btn btn-primary">Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-key mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">API Access Token</h5>
                            <p class="card-text">Generate and manage team API tokens for external access</p>
                            <a href="{{ route('team-settings.api-tokens', $team) }}" class="btn btn-primary">Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-lock mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Seguridad de contraseñas</h5>
                            <p class="card-text">Configura y rota la clave maestra del equipo para el cofre de contraseñas</p>
                            <a href="{{ route('team-settings.passwords', $team) }}" class="btn btn-primary">Configurar</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-language mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Custom Translations</h5>
                            <p class="card-text">Manage custom translations for your team</p>
                            <a href="{{ route('team-settings.custom-translations', $team) }}" class="btn btn-primary">Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-building-store mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Business Configuration') }}</h5>
                            <p class="card-text">{{ __('Configure your business details step by step') }}</p>
                            <a href="{{ route('team-settings.business-config', $team) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-shopping-bag mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Public assistant shop') }}</h5>
                            <p class="card-text">{{ __('The address uses your business name from the business configuration wizard (normalized). Published products only.') }}</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'public_shop']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-share mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Social networks') }}</h5>
                            <p class="card-text">{{ __('Connect team accounts (Meta, LinkedIn, Bluesky) for dashboard stats.') }}</p>
                            <a href="{{ route('team-settings.social', $team) }}" class="btn btn-primary">{{ __('Manage') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-phone mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Twilio Integration</h5>
                            <p class="card-text">Configure Twilio API settings for SMS and WhatsApp</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'twilio']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-lifebuoy mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Chat / Asistente') }}</h5>
                            <p class="card-text">{{ __('Asistente: modo prueba, enrutado por palabras opcional') }}</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'chat']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-scan mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('Document OCR') }}</h5>
                            <p class="card-text">{{ __('Choose local, AI, or hybrid OCR mode for the document ingestion pipeline.') }}</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'documents']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-mail mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Email Configuration</h5>
                            <p class="card-text">Configure SMTP and IMAP settings for incoming and outgoing emails</p>
                            <div class="btn-group">
                                <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'email']) }}" class="btn btn-primary">Configure</a>
                                <a href="{{ route('team.mailboxes.index', $team) }}" class="btn btn-outline-primary">Gestionar casillas</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-layout-grid-add mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Team Shortcuts</h5>
                            <p class="card-text">Configure custom shortcuts that appear in the navbar for quick access</p>
                            <a href="{{ route('team-settings.shortcuts', $team) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-world mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('WordPress Connection') }}</h5>
                            <p class="card-text">{{ __('Configure WordPress REST API (URL, user, Application Password) to manage posts and pages.') }}</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'wordpress']) }}" class="btn btn-primary">{{ __('Configure') }}</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-brand-wordpress mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">WooCommerce Integration</h5>
                            <p class="card-text">Configure WooCommerce REST API settings for store synchronization</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'woocommerce']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-users-group mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Google People & Calendar</h5>
                            <p class="card-text">Connect one Google account per team to sync contacts and calendar events.</p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                @if ($googleExternalAccount)
                                    <a href="{{ route('integrations.google.connect') }}" class="btn btn-primary">Reconnect</a>
                                    <form method="POST" action="{{ route('integrations.google.disconnect') }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger">Disconnect</button>
                                    </form>
                                @else
                                    <a href="{{ route('integrations.google.connect') }}" class="btn btn-primary">Connect</a>
                                @endif
                                <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'google']) }}" class="btn btn-label-secondary">{{ __('app.team_setting_google_sync_configure') }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-cloud-data-connection mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">{{ __('app.webdav_card_title') }}</h5>
                            <p class="card-text">{{ __('app.webdav_card_description') }}</p>
                            @if (! $webDavApiConfigured)
                                <p class="small text-warning mb-2">{{ __('app.webdav_api_not_configured') }}</p>
                            @endif
                            @if ($webDavExternalAccount)
                                <p class="small text-muted mb-2">{{ $webDavExternalAccount->provider_user_id }}</p>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    @if ($webDavApiConfigured)
                                        <form method="POST" action="{{ route('integrations.webdav.sync-all') }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success">{{ __('app.webdav_sync_all') }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('integrations.webdav.disconnect') }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger">{{ __('app.webdav_disconnect') }}</button>
                                    </form>
                                    <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'webdav']) }}" class="btn btn-label-secondary">{{ __('app.team_setting_webdav_sync_configure') }}</a>
                                </div>
                            @else
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <a href="{{ route('integrations.webdav.create-form') }}" class="btn btn-primary">{{ __('app.webdav_create_account') }}</a>
                                    <a href="{{ route('integrations.webdav.link-form') }}" class="btn btn-label-secondary">{{ __('app.webdav_link_account') }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-chart-line mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Google Analytics</h5>
                            <p class="card-text">Configure GA4 Property ID and service account for dashboard analytics</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'analytics']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-calendar-event mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Calendar Sync</h5>
                            <p class="card-text">Choose which Google calendar ID is used for sync jobs.</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'calendar']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
<script>
    function testSmtpConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-smtp`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }

    function testImapConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-imap`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }

    function testStripeConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-stripe`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }

    function testTwilioConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-twilio`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }
</script>
@endsection
