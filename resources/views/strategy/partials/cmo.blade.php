@php
    $analysis = is_array($cmoAnalysis ?? null) ? $cmoAnalysis : null;
@endphp

<div class="card mb-4" id="cmo-analysis" style="scroll-margin-top: 6rem;">
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

                    if ($block['key'] === 'business_model' && is_array($cmoCanvas ?? null))
                    {
                        $visible = true;
                    }

                    if ($block['key'] === 'value_proposition' && is_array($cmoValue ?? null))
                    {
                        $visible = true;
                    }

                    if ($block['key'] === 'empathy' && is_array($cmoEmpathy ?? null))
                    {
                        $visible = true;
                    }
                @endphp
                @if ($visible)
                    <h6 class="mb-1">{{ __('app.'.$block['title']) }}</h6>
                    <p class="text-muted small mb-2">{{ __('app.'.$block['hint']) }}</p>
                    @if ($block['key'] === 'eisenhower' && is_array($value))
                        <div class="mb-4" id="cmo-eisenhower">
                            <div class="d-flex justify-content-around mb-2 ps-5">
                                <span class="badge bg-dark rounded-pill px-3 py-2">{{ __('app.cmo_eisenhower_urgent') }}</span>
                                <span class="badge bg-dark rounded-pill px-3 py-2">{{ __('app.cmo_eisenhower_not_urgent') }}</span>
                            </div>
                            <div class="d-flex">
                                <div class="d-flex flex-column justify-content-around align-items-center me-2">
                                    <span class="badge bg-dark rounded-pill px-2 py-2" style="writing-mode: vertical-rl; transform: rotate(180deg);">{{ __('app.cmo_eisenhower_important') }}</span>
                                    <span class="badge bg-dark rounded-pill px-2 py-2" style="writing-mode: vertical-rl; transform: rotate(180deg);">{{ __('app.cmo_eisenhower_not_important') }}</span>
                                </div>
                                <div class="flex-grow-1 bg-dark">
                                    <div class="row g-1">
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 text-white text-center" style="background-color: #f15a5a;">
                                                <div class="fw-semibold mb-2">{{ __('app.cmo_eisenhower_do') }}</div>
                                                <div>{{ $value['c1'] ?? '' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 text-white text-center" style="background-color: #2f6fed;">
                                                <div class="fw-semibold mb-2">{{ __('app.cmo_eisenhower_schedule') }}</div>
                                                <div>{{ $value['c2'] ?? '' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 text-white text-center" style="background-color: #3cbe6c;">
                                                <div class="fw-semibold mb-2">{{ __('app.cmo_eisenhower_delegate') }}</div>
                                                <div>{{ $value['c3'] ?? '' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 text-center" style="background-color: #f5c542; color: #1c1410;">
                                                <div class="fw-semibold mb-2">{{ __('app.cmo_eisenhower_drop') }}</div>
                                                <div>{{ $value['c4'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif ($block['key'] === 'ansoff' && is_array($value))
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th class="text-center">{{ __('app.cmo_ansoff_current_products') }}</th>
                                        <th class="text-center">{{ __('app.cmo_ansoff_new_products') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th scope="row">{{ __('app.cmo_ansoff_current_markets') }}</th>
                                        <td class="bg-label-success">
                                            <div class="fw-medium mb-1">{{ __('app.cmo_ansoff_penetration') }}</div>
                                            <div>{{ $value['penetration'] ?? '' }}</div>
                                        </td>
                                        <td class="bg-label-info">
                                            <div class="fw-medium mb-1">{{ __('app.cmo_ansoff_product') }}</div>
                                            <div>{{ $value['product'] ?? '' }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{{ __('app.cmo_ansoff_new_markets') }}</th>
                                        <td class="bg-label-warning">
                                            <div class="fw-medium mb-1">{{ __('app.cmo_ansoff_market') }}</div>
                                            <div>{{ $value['market'] ?? '' }}</div>
                                        </td>
                                        <td class="bg-label-secondary">
                                            <div class="fw-medium mb-1">{{ __('app.cmo_ansoff_diversification') }}</div>
                                            <div>{{ $value['diversification'] ?? '' }}</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @elseif ($block['key'] === 'business_model' && is_array($cmoCanvas ?? null))
                        @php
                            $canvasText = function (string $field) use ($value): string {
                                return is_array($value) ? trim((string) ($value[$field] ?? '')) : '';
                            };
                            $canvasLines = function (string $field) use ($cmoCanvas, $canvasText): array {
                                $written = $canvasText($field);

                                if ($written !== '') {
                                    return [$written];
                                }

                                $lines = $cmoCanvas[$field] ?? [];

                                return is_array($lines) ? $lines : [];
                            };
                        @endphp
                        <div class="border mb-4" id="cmo-canvas">
                            <div class="row g-0 align-items-stretch">
                                <div class="col-12 col-lg-2 border-end border-bottom d-flex">
                                    <div class="p-3 w-100">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">8</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_partners') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('partners') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-2 border-end d-flex flex-column">
                                    <div class="p-3 border-bottom flex-grow-1">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">7</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_activities') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('activities') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="p-3 border-bottom flex-grow-1">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">6</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_resources') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('resources') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4 border-end border-bottom d-flex">
                                    <div class="p-3 w-100">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">2</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_value') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('value') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-2 border-end d-flex flex-column">
                                    <div class="p-3 border-bottom flex-grow-1">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">4</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_relationships') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('relationships') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="p-3 border-bottom flex-grow-1">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">3</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_channels') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('channels') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-2 border-bottom d-flex">
                                    <div class="p-3 w-100">
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">1</span>
                                            <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                            <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_segments') }}</span>
                                        </div>
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($canvasLines('segments') as $line)
                                                <li class="mb-1">{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-0">
                                <div class="col-12 col-lg-6 border-end p-3">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">9</span>
                                        <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                        <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_costs') }}</span>
                                    </div>
                                    <ul class="list-unstyled mb-0 small">
                                        @foreach ($canvasLines('costs') as $line)
                                            <li class="mb-1">{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="col-12 col-lg-6 p-3">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <span class="badge rounded-pill text-dark" style="background-color: #3fd0bd;">5</span>
                                        <span class="d-inline-block rounded-circle flex-shrink-0" style="width: 0.5rem; height: 0.5rem; background-color: #3fd0bd;"></span>
                                        <span class="text-uppercase fw-semibold small lh-sm">{{ __('app.cmo_bmc_revenue') }}</span>
                                    </div>
                                    <ul class="list-unstyled mb-0 small">
                                        @foreach ($canvasLines('revenue') as $line)
                                            <li class="mb-1">{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @elseif ($block['key'] === 'empathy' && is_array($cmoEmpathy ?? null))
                        @php
                            $empathyLines = function (string $field) use ($value, $cmoEmpathy): array {
                                $facts = $cmoEmpathy[$field] ?? [];

                                if (is_array($facts) && $facts !== []) {
                                    return $facts;
                                }

                                $written = is_array($value) ? trim((string) ($value[$field] ?? '')) : '';

                                return $written !== '' ? [$written] : [];
                            };
                        @endphp
                        <div class="mb-4 border border-2" id="cmo-empathy-map" style="border-color: #6b7280;">
                            <div class="position-relative" style="min-height: 22rem;">
                                <svg class="position-absolute top-0 start-0 w-100 h-100" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                    <line x1="0" y1="0" x2="100" y2="100" stroke="#6b7280" stroke-width="1.25" vector-effect="non-scaling-stroke"></line>
                                    <line x1="100" y1="0" x2="0" y2="100" stroke="#6b7280" stroke-width="1.25" vector-effect="non-scaling-stroke"></line>
                                </svg>
                                <div class="position-absolute text-center" style="top: 4%; left: 22%; width: 56%;">
                                    <div class="fw-semibold text-secondary">{{ __('app.cmo_empathy_thinks') }}</div>
                                    @foreach ($empathyLines('thinks') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute" style="top: 38%; left: 2%; width: 27%;">
                                    <div class="fw-semibold text-secondary">{{ __('app.cmo_empathy_hears') }}</div>
                                    @foreach ($empathyLines('hears') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute text-end" style="top: 38%; right: 2%; width: 27%;">
                                    <div class="fw-semibold text-secondary">{{ __('app.cmo_empathy_sees') }}</div>
                                    @foreach ($empathyLines('sees') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute text-center" style="bottom: 5%; left: 28%; width: 44%;">
                                    <div class="fw-semibold text-secondary">{{ __('app.cmo_empathy_says') }}</div>
                                    @foreach ($empathyLines('says') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute top-50 start-50 translate-middle bg-white d-flex align-items-center justify-content-center" style="width: 4.5rem; height: 4.5rem; z-index: 1;">
                                    <i class="ti ti-user" style="font-size: 2.5rem; color: #8bc34a;"></i>
                                </div>
                            </div>
                            <div class="row g-0 border-top border-2" style="border-color: #6b7280;">
                                <div class="col-md-6 border-end p-3" style="border-color: #6b7280;">
                                    <div class="fw-semibold text-secondary mb-1">{{ __('app.cmo_empathy_pains') }}</div>
                                    @foreach ($empathyLines('pains') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="col-md-6 p-3">
                                    <div class="fw-semibold text-secondary mb-1">{{ __('app.cmo_empathy_gains') }}</div>
                                    @foreach ($empathyLines('gains') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif ($block['key'] === 'value_proposition' && is_array($cmoValue ?? null))
                        @php
                            $valueLines = function (string $field) use ($value, $cmoValue): array {
                                $written = is_array($value) ? trim((string) ($value[$field] ?? '')) : '';

                                if ($written !== '') {
                                    return [$written];
                                }

                                $lines = $cmoValue[$field] ?? [];

                                return is_array($lines) ? $lines : [];
                            };
                        @endphp
                        <div class="d-flex flex-column flex-lg-row align-items-center justify-content-center gap-2 mb-4" id="cmo-value-canvas">
                            <div class="position-relative border border-2 overflow-hidden" style="width: min(100%, 26rem); aspect-ratio: 1; border-color: #1b3a4b;">
                                <svg class="position-absolute top-0 start-0 w-100 h-100" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                    <line x1="0" y1="0" x2="100" y2="100" stroke="#1b3a4b" stroke-width="1.25" vector-effect="non-scaling-stroke"></line>
                                    <line x1="100" y1="0" x2="0" y2="100" stroke="#1b3a4b" stroke-width="1.25" vector-effect="non-scaling-stroke"></line>
                                </svg>
                                <div class="position-absolute text-center" style="top: 6%; left: 28%; width: 52%;">
                                    <div class="fw-semibold small"><i class="ti ti-rocket text-warning me-1"></i>{{ __('app.cmo_vpc_gain_creators') }}</div>
                                    @foreach ($valueLines('gain_creators') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute" style="top: 34%; left: 3%; width: 38%;">
                                    <div class="fw-semibold small"><i class="ti ti-shopping-bag text-primary me-1"></i>{{ __('app.cmo_vpc_products') }}</div>
                                    @foreach ($valueLines('products') as $line)
                                        <div class="lh-sm" style="font-size: .75rem;">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute text-center" style="bottom: 6%; left: 22%; width: 56%;">
                                    <div class="fw-semibold small"><i class="ti ti-pill text-info me-1"></i>{{ __('app.cmo_vpc_pain_relievers') }}</div>
                                    @foreach ($valueLines('pain_relievers') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute top-50 start-50 translate-middle bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem; z-index: 1;">
                                    <i class="ti ti-gift text-warning"></i>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-center text-dark" aria-hidden="true">
                                <svg class="d-none d-lg-block" width="52" height="24" viewBox="0 0 52 24">
                                    <line x1="0" y1="12" x2="36" y2="12" stroke="#1b3a4b" stroke-width="2"></line>
                                    <polyline points="30,5 44,12 30,19" fill="none" stroke="#1b3a4b" stroke-width="2"></polyline>
                                </svg>
                                <i class="ti ti-arrow-down d-lg-none"></i>
                            </div>
                            <div class="position-relative rounded-circle border border-2 overflow-hidden" style="width: min(100%, 26rem); aspect-ratio: 1; border-color: #1b3a4b;">
                                <svg class="position-absolute top-0 start-0 w-100 h-100" viewBox="0 0 100 100" aria-hidden="true">
                                    <line x1="50" y1="50" x2="88" y2="18" stroke="#1b3a4b" stroke-width="1.25"></line>
                                    <line x1="50" y1="50" x2="88" y2="82" stroke="#1b3a4b" stroke-width="1.25"></line>
                                </svg>
                                <div class="position-absolute text-center" style="top: 16%; left: 14%; width: 36%;">
                                    <div class="fw-semibold small"><i class="ti ti-mood-smile text-warning me-1"></i>{{ __('app.cmo_vpc_gains') }}</div>
                                    @foreach ($valueLines('gains') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute text-center" style="top: 38%; right: 4%; width: 34%;">
                                    <div class="fw-semibold small"><i class="ti ti-user text-success me-1"></i>{{ __('app.cmo_vpc_jobs') }}</div>
                                    @foreach ($valueLines('jobs') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <div class="position-absolute text-center" style="bottom: 14%; left: 12%; width: 36%;">
                                    <div class="fw-semibold small"><i class="ti ti-mood-sad me-1"></i>{{ __('app.cmo_vpc_pains') }}</div>
                                    @foreach ($valueLines('pains') as $line)
                                        <div class="small lh-sm">{{ $line }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif (is_array($fields))
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
