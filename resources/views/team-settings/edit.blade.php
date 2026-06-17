@extends('layouts/layoutMaster')

@php
    use App\Support\TeamSettingsLabels;
@endphp

@section('title', TeamSettingsLabels::groupTitle($group ?? ''))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
@endsection

@section('content')
@php
    $groupTitle = TeamSettingsLabels::groupTitle($group ?? '');
    $groupSubtitle = TeamSettingsLabels::groupSubtitle($group ?? '');
    $headerActions = '';
    if (($group ?? '') === 'cuentica') {
        $headerActions = '<button type="button" id="btnTestCuentica" class="btn btn-info waves-effect waves-light" data-url="'.e(route('team-settings.test-cuentica', $team)).'"><i class="ti ti-plug-connected me-1"></i>'.e(__('Probar conexión')).'</button>';
    }
@endphp

@include('team-settings.partials.header', [
    'team' => $team,
    'title' => $groupTitle,
    'subtitle' => $groupSubtitle,
    'actions' => $headerActions,
])

@if (($group ?? '') === 'cuentica')
    <div id="cuenticaTestResult" class="mb-3"></div>
@endif

    <div class="row">
        <div class="col-md-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-warning">
                    {{ session('error') }}
                </div>
            @endif

            @if (($group ?? '') === 'fiscal')
                <div class="alert alert-info mb-4">
                    {{ __('Después de elegir la plataforma, configura las credenciales del proveedor:') }}
                    <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'cuentica']) }}" class="alert-link">{{ __('Cuéntica') }}</a>
                    {{ __('(España). ARCA estará disponible próximamente.') }}
                </div>
            @endif

            <form id="formTeamSettings" method="POST" action="{{ route('team-settings.update', $team) }}">
                @csrf
                @method('PUT')

                @foreach ($settings as $groupKey => $group)
                    <div class="card mb-4">
                        <h5 class="card-header d-flex align-items-center">
                            <i class="{{ $group['icon'] ?? 'ti ti-settings' }} me-2"></i>
                            {{ $group['title'] }}
                        </h5>
                        <div class="card-body">
                            @php
                                $visibleSettings = $group['settings'];
                                if ($groupKey === 'email') {
                                    $visibleSettings = array_filter(
                                        $group['settings'],
                                        fn ($setting) => ! in_array($setting['section'] ?? '', ['outgoing', 'incoming'], true),
                                    );
                                }

                                // Group fields by section and row to determine column classes
                                $fieldsByRow = [];
                                foreach ($visibleSettings as $key => $setting) {
                                    $section = $setting['section'] ?? 'default';
                                    $row = $setting['row'] ?? 1;
                                    $fieldsByRow[$section][$row][] = $key;
                                }
                            @endphp

                            <div class="row">
                                @php $currentSection = null; @endphp
                                @if (($groupKey ?? '') === 'chat')
                                    <div class="col-12 mb-2">
                                        <small class="text-muted text-uppercase">{{ __('Settings') }}</small>
                                    </div>
                                @endif
                                @foreach ($visibleSettings as $key => $setting)
                                    @if(isset($setting['section']) && $setting['section'] !== $currentSection)
                                        @if($currentSection !== null)
                                            {{-- Close previous row and add separator --}}
                                            </div>
                                            <hr class="my-4">
                                            <div class="row">
                                        @endif
                                        @php $currentSection = $setting['section']; @endphp

                                        {{-- Add section title --}}
                                        @php $sectionTitle = TeamSettingsLabels::sectionTitle($setting['section'], $groupKey); @endphp
                                        @if($setting['section'] === 'team_sender' && $groupKey === 'email')
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted mb-1">{{ __('app.team_setting_team_sender_title') }}</h6>
                                                <p class="small text-muted mb-0">{{ __('app.team_setting_team_sender_intro') }}</p>
                                                @if ($team->hasTeamEmailSenderConfigured())
                                                    <p class="small mb-0 mt-2">
                                                        <span class="badge bg-label-primary">
                                                            {{ $team->getTeamEmailSender()['from_name'] }}
                                                            &lt;{{ $team->getTeamEmailSender()['from_address'] }}&gt;
                                                        </span>
                                                    </p>
                                                @else
                                                    <p class="small text-warning mb-0 mt-2">{{ __('app.team_setting_team_sender_not_configured') }}</p>
                                                @endif
                                            </div>
                                        @elseif($setting['section'] === 'mailer_sender' && $groupKey === 'email')
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted mb-1">{{ __('app.team_setting_mailer_sender_title') }}</h6>
                                                <p class="small text-muted mb-0">{{ __('app.team_setting_mailer_sender_intro') }}</p>
                                                @if ($team->hasMailerSenderOverrideConfigured())
                                                    <p class="small mb-0 mt-2">
                                                        <span class="badge bg-label-info">
                                                            {{ $team->getMailerEmailSender()['from_name'] }}
                                                            &lt;{{ $team->getMailerEmailSender()['from_address'] }}&gt;
                                                        </span>
                                                    </p>
                                                @elseif ($team->hasTeamEmailSenderConfigured())
                                                    <p class="small text-muted mb-0 mt-2">{{ __('app.team_setting_mailer_uses_team_sender') }}</p>
                                                @endif
                                            </div>
                                        @elseif($sectionTitle)
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted mb-0">{{ $sectionTitle }}</h6>
                                            </div>
                                        @elseif($setting['section'] === 'routing')
                                            <div class="col-12 mb-2">
                                                <p class="small text-muted mb-0">
                                                    <i class="ti ti-sparkles me-1"></i>{{ __('Default assistant flow (AI discovery)') }} ·
                                                    <i class="ti ti-webhook me-1"></i>{{ __('How flows are chosen: AI asks vs automatic keyword routing.') }}
                                                </p>
                                            </div>
                                        @elseif($setting['section'] === 'performance_insights')
                                            <div class="col-12 mb-3">
                                                <h6 class="text-muted mb-0">{{ __('app.performance_insights_menu') }}</h6>
                                            </div>
                                        @endif
                                    @endif

                                    @php
                                        // Determine column class based on field type and position in row
                                        $section = $setting['section'] ?? 'default';
                                        $row = $setting['row'] ?? 1;
                                        $fieldsInRow = count($fieldsByRow[$section][$row] ?? []);

                                        // Special layout for server configuration rows (host, port, encryption)
                                        if ($fieldsInRow === 3 && (str_contains($key, 'host') || str_contains($key, 'port') || str_contains($key, 'encryption'))) {
                                            if (str_contains($key, 'host')) {
                                                $colClass = 'col-md-6'; // Host gets 50%
                                            } else {
                                                $colClass = 'col-md-3'; // Port and Encryption get 25% each
                                            }
                                        } else {
                                            // Standard layout: 2 fields = 50% each, 3 fields = 33% each
                                            $colClass = $fieldsInRow === 3 ? 'col-md-4' : 'col-md-6';
                                        }
                                    @endphp

                                    <div class="mb-3 {{ $colClass }}">
                                        <label for="{{ $key }}" class="form-label">
                                            {{ $setting['label'] }}
                                            @if (! empty($setting['required']))
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>

                                        @if($setting['type'] === 'select' && isset($setting['options']))
                                            <select class="form-select @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                id="{{ $key }}"
                                                name="{{ $groupKey }}[{{ $key }}]">
                                                @foreach($setting['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" {{ $setting['value'] == $optionValue ? 'selected' : '' }}>
                                                        {{ $optionLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($setting['type'] === 'checkbox')
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                    type="checkbox"
                                                    id="{{ $key }}"
                                                    name="{{ $groupKey }}[{{ $key }}]"
                                                    value="1"
                                                    {{ $setting['value'] == '1' ? 'checked' : '' }}
                                                />
                                                <label class="form-check-label" for="{{ $key }}">{{ __('Enable') }}</label>
                                            </div>
                                        @elseif($setting['type'] === 'textarea')
                                            <textarea class="form-control @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                id="{{ $key }}"
                                                name="{{ $groupKey }}[{{ $key }}]"
                                                rows="3"
                                                placeholder="{{ $setting['placeholder'] ?? __('Enter :label', ['label' => strtolower($setting['label'])]) }}"
                                            >{{ old("{$groupKey}.{$key}", $setting['value']) }}</textarea>
                                        @elseif($setting['type'] === 'readonly')
                                            <div class="input-group">
                                                <input class="form-control bg-light"
                                                    type="text"
                                                    id="{{ $key }}"
                                                    value="{{ $setting['value'] }}"
                                                    readonly />
                                                <span class="input-group-text cursor-pointer" onclick="copyToClipboard('{{ $setting['value'] }}', this)">
                                                    <i class="ti ti-copy"></i>
                                                </span>
                                            </div>
                                        @else
                                            <div class="input-group input-group-merge has-validation">
                                                <input class="form-control @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                    type="{{ $setting['type'] }}" id="{{ $key }}"
                                                    name="{{ $groupKey }}[{{ $key }}]"
                                                    value="{{ old("{$groupKey}.{$key}", $setting['value']) }}"
                                                    placeholder="{{ $setting['placeholder'] ?? __('Enter :label', ['label' => strtolower($setting['label'])]) }}"
                                                    @if (! empty($setting['required'])) required @endif />
                                                @if ($setting['type'] === 'password')
                                                    <span class="input-group-text cursor-pointer toggle-password"><i
                                                            class="ti ti-eye-off"></i></span>
                                                @endif
                                            </div>
                                        @endif

                                        @error("{$groupKey}.{$key}")
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @if (!empty($setting['help'] ?? null))
                                            <div class="form-text">{{ $setting['help'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </form>

            @if (($group ?? '') === 'chat')
                <div class="card mb-4">
                    <h5 class="card-header d-flex align-items-center">
                        <i class="ti ti-file-text me-2"></i>
                        {{ __('Default assistant flows (module prompts)') }}
                    </h5>
                    <div class="card-body">
                        <p class="text-muted small mb-3 mb-md-2">
                            {{ __('Creates the default assistant flow prompts for this team (citas, contactos, catálogo, campañas News, tareas) if they are missing. Your existing custom prompt text in Prompts is not overwritten.') }}
                        </p>
                        <form method="post" action="{{ route('team-settings.chat.seed-default-assistant-prompts', $team) }}" class="d-flex flex-wrap align-items-center gap-2">
                            @csrf
                            <button type="submit" class="btn btn-label-primary">
                                <i class="ti ti-refresh me-1"></i>{{ __('Ensure default assistant prompts') }}
                            </button>
                            @if (auth()->user()->currentTeam?->hasModule('prompts'))
                                <a href="{{ route('prompt-list') }}" class="btn btn-sm btn-outline-secondary">{{ __('Open prompt list') }}</a>
                            @endif
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('page-script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(toggle => {
                toggle.addEventListener('click', e => {
                    const input = e.target.closest('.input-group').querySelector('input');
                    const icon = e.target.closest('.input-group').querySelector('i');

                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('ti-eye-off');
                        icon.classList.add('ti-eye');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('ti-eye');
                        icon.classList.add('ti-eye-off');
                    }
                });
            });
        });

        // Cuéntica connection test
        (function() {
            const btn = document.getElementById('btnTestCuentica');
            if (!btn) {
                return;
            }

            const result = document.getElementById('cuenticaTestResult');
            const originalText = btn.innerHTML;

            btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>{{ __('Probando...') }}';
                result.innerHTML = '';

                fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    const cssClass = data.success ? 'alert-success' : 'alert-warning';
                    result.innerHTML = '<div class="alert ' + cssClass + ' mb-0">' + (data.message || '') + '</div>';
                })
                .catch(error => {
                    console.error('Cuéntica test connection error:', error);
                    result.innerHTML = '<div class="alert alert-danger mb-0">{{ __('Error inesperado al probar la conexión.') }}</div>';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        })();

        // Copy to clipboard function
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success state
                const icon = button.querySelector('i');
                icon.classList.remove('ti-copy');
                icon.classList.add('ti-check', 'text-success');

                // Reset to original state after 2 seconds
                setTimeout(() => {
                    icon.classList.remove('ti-check', 'text-success');
                    icon.classList.add('ti-copy');
                }, 2000);
            }).catch(function(err) {
                console.error('Error copying to clipboard: ', err);
            });
        }
    </script>
@endsection
