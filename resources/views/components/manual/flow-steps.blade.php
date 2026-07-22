@props([
    'steps' => [],
])

<div class="d-flex flex-column flex-md-row flex-wrap gap-2 align-items-stretch mb-4">
    @foreach ($steps as $index => $step)
        <div class="card border flex-fill mb-0" style="min-width: 140px; max-width: 220px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge rounded-pill bg-primary">{{ $index + 1 }}</span>
                    @if (! empty($step['role']))
                        <span class="badge bg-label-{{ $step['role'] === 'admin' ? 'primary' : 'secondary' }}">{{ ucfirst($step['role']) }}</span>
                    @endif
                </div>
                <h6 class="mb-1">{{ __($step['title']) }}</h6>
                @if (! empty($step['body']))
                    <p class="small text-muted mb-0">{{ __($step['body']) }}</p>
                @endif
            </div>
        </div>
        @if (! $loop->last)
            <div class="d-none d-md-flex align-items-center text-muted px-1">
                <i class="ti ti-arrow-right ti-lg"></i>
            </div>
        @endif
    @endforeach
</div>
