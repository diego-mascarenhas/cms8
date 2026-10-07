@php
    $analysis = is_array($cmoAnalysis ?? null) ? $cmoAnalysis : null;
@endphp

<div class="card mb-4" id="cmo-analysis">
    <div class="card-header">
        <h5 class="card-title m-0">
            {{ __('app.cmo_analysis_title') }}
            <a href="{{ route('help.cfo-analysis') }}#cmo" target="_blank" rel="noopener" class="text-muted ms-1" title="{{ __('help_cfo.cmo_title') }}">
                <i class="ti ti-info-circle"></i>
            </a>
        </h5>
        <p class="text-muted small mb-0">{{ __('app.cmo_analysis_hint') }} <a href="https://adquiria.net/" target="_blank" rel="noopener">Adquiria</a>.</p>
    </div>
    <div class="card-body">
        @if ($analysis === null)
            <p class="text-muted mb-0">{{ __('app.cmo_analysis_empty') }}</p>
        @elseif (! \App\Services\Marketing\CmoBriefService::hasStoredSections($analysis) && filled($analysis['brief'] ?? null))
            <div style="white-space: pre-wrap;">{{ $analysis['brief'] }}</div>
        @else
            @foreach (\App\Services\Marketing\CmoBriefService::blocks() as $block)
                @php
                    $value = $analysis[$block['key']] ?? '';
                    $fields = $block['fields'] ?? null;
                    $visible = is_array($fields)
                        ? implode('', is_array($value) ? $value : []) !== ''
                        : filled($value);
                @endphp
                @if ($visible)
                    <h6 class="mb-1">{{ __('app.'.$block['title']) }}</h6>
                    <p class="text-muted small mb-2">{{ __('app.'.$block['hint']) }}</p>
                    @if (is_array($fields))
                        <div class="row g-3 mb-4">
                            @foreach ($fields as $field => $label)
                                @if (filled($value[$field] ?? null))
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small mb-1">{{ __('app.'.$label) }}</div>
                                            <div>{{ $value[$field] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="mb-4">{{ $value }}</p>
                    @endif
                @endif
            @endforeach
            @if (! empty($analysis['generated_at']))
                <p class="text-muted small mb-0">{{ __('app.cfo_analysis_projection_updated', ['date' => \Carbon\Carbon::parse($analysis['generated_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i')]) }}</p>
            @endif
        @endif
    </div>
</div>
