@if (! empty($subsistence['banners'] ?? null))
    <div class="card mb-4" id="cfo-subsistence">
        <div class="card-header">
            <h5 class="card-title m-0">
                {{ __('app.subsistence_title') }}
                <a href="{{ route('help.cfo-analysis') }}#subsistence" target="_blank" rel="noopener" class="text-muted ms-1" title="{{ __('help_cfo.subsistence_title') }}">
                    <i class="ti ti-info-circle"></i>
                </a>
            </h5>
            <p class="text-muted small mb-0">{{ __('app.subsistence_hint') }}</p>
        </div>
        <div class="card-body">
            @foreach ($subsistence['banners'] as $banner)
                <div class="alert alert-{{ $banner['level'] }} d-flex align-items-start {{ $loop->last ? 'mb-0' : 'mb-3' }}" role="alert">
                    <i class="ti {{ $banner['icon'] }} ti-md me-2 mt-1" aria-hidden="true"></i>
                    <div>
                        <div class="fw-semibold mb-1">{{ $banner['title'] }}</div>
                        <div>{{ $banner['body'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
