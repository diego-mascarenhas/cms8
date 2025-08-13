@extends('layouts/layoutMaster')

@section('title', 'Team Settings')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Settings/</span> {{ isset($group) ? ucfirst($group) : 'Configuration' }}</h4>
        <p class="text-muted">Configure {{ isset($group) ? strtolower($group) : 'team' }} settings</p>
    </div>
</div>

    <div class="row">
        <div class="col-md-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
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
                            <div class="row">
                                @foreach ($group['settings'] as $key => $setting)
                                    <div class="mb-3 col-md-6">
                                        <label for="{{ $key }}" class="form-label">{{ $setting['label'] }}</label>

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
                                                <label class="form-check-label" for="{{ $key }}">Enable</label>
                                            </div>
                                        @elseif($setting['type'] === 'textarea')
                                            <textarea class="form-control @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                id="{{ $key }}"
                                                name="{{ $groupKey }}[{{ $key }}]"
                                                rows="3"
                                                placeholder="Enter {{ strtolower($setting['label']) }}"
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
                                            <div class="input-group input-group-merge">
                                                <input class="form-control @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                    type="{{ $setting['type'] }}" id="{{ $key }}"
                                                    name="{{ $groupKey }}[{{ $key }}]"
                                                    value="{{ old("{$groupKey}.{$key}", $setting['value']) }}"
                                                    placeholder="Enter {{ strtolower($setting['label']) }}" />
                                                @if ($setting['type'] === 'password')
                                                    <span class="input-group-text cursor-pointer toggle-password"><i
                                                            class="ti ti-eye-off"></i></span>
                                                @endif
                                            </div>
                                        @endif

                                        @error("{$groupKey}.{$key}")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">Save Changes</button>
                                <a href="{{ route('team-settings.index', $team) }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </form>
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
