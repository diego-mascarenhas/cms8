@extends('layouts/layoutMaster')

@section('title', 'Configuración de Cuenta')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
@endsection

@section('content')
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Configuración/</span> Stripe
    </h4>

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
                        <h5 class="card-header">
                            <i class="{{ $group['icon'] ?? 'ti ti-settings' }} me-2"></i>
                            {{ $group['title'] }}
                        </h5>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($group['settings'] as $key => $setting)
                                    <div class="mb-3 col-md-6">
                                        <label for="{{ $key }}" class="form-label">{{ $setting['label'] }}</label>
                                        <div class="input-group input-group-merge">
                                            <input class="form-control @error("{$groupKey}.{$key}") is-invalid @enderror"
                                                type="{{ $setting['type'] }}" id="{{ $key }}"
                                                name="{{ $groupKey }}[{{ $key }}]"
                                                value="{{ old("{$groupKey}.{$key}", $setting['value']) }}"
                                                placeholder="Enter {{ strtolower($setting['label']) }}" />
                                            @if ($setting['type'] === 'password')
                                                <span class="input-group-text cursor-pointer"><i
                                                        class="ti ti-eye-off"></i></span>
                                            @endif
                                        </div>
                                        @error("{$groupKey}.{$key}")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">Guardar</button>
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
            document.querySelectorAll('.input-group-text').forEach(toggle => {
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
    </script>
@endsection
