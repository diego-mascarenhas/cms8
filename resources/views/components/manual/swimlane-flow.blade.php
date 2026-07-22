@props([
    'lanes' => [],
])

@php
    $roleClass = [
        'admin' => 'primary',
        'collaborator' => 'secondary',
        'client' => 'success',
    ];
@endphp

<div {{ $attributes->class(['manual-swimlanes mb-4']) }}>
    <div class="row g-3">
        @foreach ($lanes as $lane)
            @php
                $role = $lane['role'] ?? 'admin';
                $color = $roleClass[$role] ?? 'dark';
            @endphp
            <div class="col-lg-4">
                <div class="card h-100 border-{{ $color }}">
                    <div class="card-header bg-label-{{ $color }} d-flex align-items-center gap-2">
                        <span class="badge bg-{{ $color }}">{{ ucfirst($role) }}</span>
                        <strong>{{ __($lane['title'] ?? ucfirst($role)) }}</strong>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-stretch gap-2">
                            @foreach (($lane['steps'] ?? []) as $stepIndex => $step)
                                <div class="border rounded p-2 bg-body">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge rounded-pill bg-{{ $color }}">{{ $stepIndex + 1 }}</span>
                                        <div>
                                            <div class="fw-semibold small">{{ __($step['label']) }}</div>
                                            @if (! empty($step['body']))
                                                <div class="text-muted small">{{ __($step['body']) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if (! $loop->last)
                                    <div class="text-center text-muted py-0">
                                        <i class="ti ti-arrow-down"></i>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @if (! empty($lane['blocked']))
                            <hr>
                            <p class="small text-muted mb-1">{{ __('Sin acceso') }}</p>
                            <ul class="small text-muted mb-0 ps-3">
                                @foreach ($lane['blocked'] as $blocked)
                                    <li>{{ __($blocked) }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
