@props([
    'title' => null,
    'nodes' => [],
])

@php
    $roleClass = [
        'admin' => 'primary',
        'collaborator' => 'secondary',
        'client' => 'success',
    ];
@endphp

<div {{ $attributes->class(['manual-flowchart mb-4']) }}>
    @if ($title)
        <h6 class="text-uppercase text-muted small mb-3">{{ __($title) }}</h6>
    @endif

    <div class="d-flex flex-column align-items-center">
        @foreach ($nodes as $index => $node)
            @php
                $shape = $node['shape'] ?? 'process';
                $role = $node['role'] ?? null;
                $color = $roleClass[$role] ?? 'dark';
                $yesNo = $node['branches'] ?? null;
            @endphp

            @if ($shape === 'terminal')
                <div class="px-4 py-2 rounded-pill border border-{{ $color }} bg-label-{{ $color }} text-center fw-semibold" style="min-width: 180px;">
                    {{ __($node['label']) }}
                </div>
            @elseif ($shape === 'decision')
                <div class="position-relative text-center" style="width: 220px;">
                    <div class="border border-warning bg-warning bg-opacity-10 d-flex align-items-center justify-content-center mx-auto"
                         style="width: 160px; height: 160px; clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);">
                        <div class="px-3 small fw-semibold" style="max-width: 110px;">
                            {{ __($node['label']) }}
                        </div>
                    </div>
                    @if ($role)
                        <span class="badge bg-label-{{ $color }} position-absolute top-0 start-50 translate-middle-x">{{ ucfirst($role) }}</span>
                    @endif
                </div>
            @elseif ($shape === 'note')
                <div class="alert alert-secondary py-2 px-3 mb-0 small text-center" style="max-width: 360px;">
                    {{ __($node['label']) }}
                </div>
            @else
                <div class="card border-{{ $color }} shadow-none mb-0" style="min-width: 240px; max-width: 360px;">
                    <div class="card-body py-3 px-3 text-center">
                        @if ($role)
                            <span class="badge bg-label-{{ $color }} mb-2">{{ ucfirst($role) }}</span>
                        @endif
                        <div class="fw-semibold">{{ __($node['label']) }}</div>
                        @if (! empty($node['body']))
                            <div class="small text-muted mt-1">{{ __($node['body']) }}</div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($yesNo)
                <div class="row g-3 w-100 justify-content-center mt-2 mb-1" style="max-width: 720px;">
                    @foreach ($yesNo as $branch)
                        <div class="col-md-5">
                            <div class="text-center small text-muted mb-1">{{ __($branch['when'] ?? '') }}</div>
                            <div class="card border h-100 mb-0">
                                <div class="card-body py-2 text-center small">
                                    @if (! empty($branch['role']))
                                        <span class="badge bg-label-{{ $roleClass[$branch['role']] ?? 'dark' }} mb-1">{{ ucfirst($branch['role']) }}</span>
                                    @endif
                                    <div class="fw-semibold">{{ __($branch['label']) }}</div>
                                    @if (! empty($branch['body']))
                                        <div class="text-muted mt-1">{{ __($branch['body']) }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! $loop->last)
                <div class="d-flex flex-column align-items-center py-1 text-muted">
                    <i class="ti ti-arrow-down ti-lg"></i>
                    @if (! empty($node['edge']))
                        <span class="badge bg-label-dark mb-1">{{ __($node['edge']) }}</span>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
