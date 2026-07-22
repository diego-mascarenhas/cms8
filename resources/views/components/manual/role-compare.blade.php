@props([
    'section' => null,
])

@php
    $roles = $section
        ? \App\Support\ManualDocumentation::rolesFor($section)
        : null;
    $hasClient = $roles && ! empty($roles['client']);
    $col = $hasClient ? 'col-lg-4' : 'col-md-6';
@endphp

@if ($roles)
<div class="row g-3 mt-2 mb-4">
    <div class="{{ $col }}">
        <div class="card h-100 border border-primary">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge bg-primary">Admin</span>
                <h6 class="mb-0">{{ __('Administrador') }}</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 ps-3">
                    @foreach ($roles['admin'] as $item)
                        <li class="mb-2">{{ __($item) }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="{{ $col }}">
        <div class="card h-100 border border-secondary">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge bg-label-secondary">Collaborator</span>
                <h6 class="mb-0">{{ __('Colaborador') }}</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 ps-3">
                    @foreach ($roles['collaborator'] as $item)
                        <li class="mb-2">{{ __($item) }}</li>
                    @endforeach
                </ul>
                @if (! empty($roles['collaborator_blocked']))
                    <hr>
                    <p class="small text-muted mb-1">{{ __('Sin acceso:') }}</p>
                    <ul class="mb-0 ps-3 small text-muted">
                        @foreach ($roles['collaborator_blocked'] as $item)
                            <li>{{ __($item) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @if ($hasClient)
        <div class="{{ $col }}">
            <div class="card h-100 border border-success">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="badge bg-success">Client</span>
                    <h6 class="mb-0">{{ __('Cliente final') }}</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        @foreach ($roles['client'] as $item)
                            <li class="mb-2">{{ __($item) }}</li>
                        @endforeach
                    </ul>
                    @if (! empty($roles['client_blocked']))
                        <hr>
                        <p class="small text-muted mb-1">{{ __('Sin acceso:') }}</p>
                        <ul class="mb-0 ps-3 small text-muted">
                            @foreach ($roles['client_blocked'] as $item)
                                <li>{{ __($item) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endif
