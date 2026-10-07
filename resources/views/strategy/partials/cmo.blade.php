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
        @if (($cmoRun['state'] ?? '') === 'running')
            <style>
                .cmo-run .ai-loader-overlay { position: relative; overflow: hidden; border-radius: 1rem; background: linear-gradient(135deg, var(--bs-body-bg, #fff) 0%, var(--bs-secondary-bg, #f8f9fa) 100%); border: 1px solid var(--bs-border-color, #e9ecef); }
                .cmo-run .ai-loader-core { position: relative; display: flex; align-items: center; justify-content: center; width: 80px; height: 80px; margin: 0 auto 1.25rem; }
                .cmo-run .ai-loader-ring { position: absolute; inset: 0; border-radius: 50%; border: 2px solid transparent; border-top-color: rgba(105, 108, 255, 0.9); animation: cmo-run-spin 1s linear infinite; }
                .cmo-run .ai-loader-ring:nth-child(2) { inset: 8px; animation-duration: 1.4s; animation-direction: reverse; }
                .cmo-run .ai-loader-ring:nth-child(3) { inset: 16px; animation-duration: 1.8s; }
                .cmo-run .ai-loader-icon { position: relative; font-size: 1.75rem; color: rgba(105, 108, 255, 0.95); }
                .cmo-run .ai-loader-dots { display: inline-flex; gap: 6px; margin-top: 0.5rem; }
                .cmo-run .ai-loader-dots span { width: 6px; height: 6px; border-radius: 50%; background: rgba(105, 108, 255, 0.8); animation: cmo-run-dot 1.2s ease-in-out infinite both; }
                .cmo-run .ai-loader-dots span:nth-child(2) { animation-delay: 0.2s; }
                .cmo-run .ai-loader-dots span:nth-child(3) { animation-delay: 0.4s; }
                @keyframes cmo-run-spin { to { transform: rotate(360deg); } }
                @keyframes cmo-run-dot { 0%, 80%, 100% { opacity: 0.3; } 40% { opacity: 1; } }
            </style>
            <div id="cmo-run" class="cmo-run mb-4" data-status-url="{{ route('strategy.analysis.cmo-status', ['year' => now()->year]) }}">
                <div class="d-flex justify-content-center">
                    <div class="ai-loader-overlay p-4 p-md-5 text-center">
                        <div class="ai-loader-core">
                            <span class="ai-loader-ring" aria-hidden="true"></span>
                            <span class="ai-loader-ring" aria-hidden="true"></span>
                            <span class="ai-loader-ring" aria-hidden="true"></span>
                            <i class="ti ti-cpu ai-loader-icon" aria-hidden="true"></i>
                        </div>
                        <h6 class="mb-1 fw-semibold text-body">{{ __('app.cmo_running_title') }}</h6>
                        <p class="mb-0 small text-muted" id="cmo-run-label">{{ $cmoRun['message'] }}</p>
                        <div class="ai-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                    </div>
                </div>
            </div>
            <script>
                (function () {
                    const root = document.getElementById('cmo-run');
                    const label = document.getElementById('cmo-run-label');
                    if (!root) return;
                    const tick = function () {
                        fetch(root.dataset.statusUrl, { headers: { 'Accept': 'application/json' } })
                            .then(function (response) { return response.ok ? response.json() : null; })
                            .then(function (data) {
                                if (!data) return;
                                if (label && data.message) label.textContent = data.message;
                                if (data.state === 'done' || data.state === 'failed') window.location.reload();
                            });
                    };
                    window.setInterval(tick, 3000);
                })();
            </script>
        @elseif (($cmoRun['state'] ?? '') === 'failed')
            <div class="alert alert-danger" role="alert">{{ __('app.cmo_phase_failed') }}</div>
        @endif

        @if (is_array($analysis) && \App\Services\Marketing\CmoBriefService::hasStoredSections($analysis))
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
        @elseif (is_array($analysis) && filled($analysis['brief'] ?? null))
            <div style="white-space: pre-wrap;">{{ $analysis['brief'] }}</div>
        @elseif (($cmoRun['state'] ?? '') !== 'running')
            <p class="text-muted mb-0">{{ __('app.cmo_analysis_empty') }}</p>
        @endif
    </div>
</div>
