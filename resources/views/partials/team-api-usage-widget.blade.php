@if (auth()->user()->hasRole(['root', 'admin']) && is_array($tokenStats ?? null))
    <div class="card h-100 border shadow-sm">
        <div class="card-body p-3">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div class="min-w-0">
                    <h6 class="mb-0 text-body fw-semibold">Uso de API & Ahorro</h6>
                    <small class="text-muted" style="font-size: 0.75rem;">Optimización de costos</small>
                </div>
                <div class="d-flex flex-shrink-0 gap-1">
                    <a href="{{ route('assistant.activity') }}" class="btn btn-sm btn-icon btn-label-secondary waves-effect" title="Ver actividad AI" aria-label="Ver actividad AI">
                        <i class="ti ti-activity ti-sm"></i>
                    </a>
                    <a href="{{ route('assistant.documents') }}" class="btn btn-sm btn-icon btn-label-secondary waves-effect" title="Ver documentos procesados" aria-label="Ver documentos procesados">
                        <i class="ti ti-file-search ti-sm"></i>
                    </a>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <div class="rounded border bg-body p-2 text-center">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Llamadas</small>
                        <span class="fw-semibold text-body d-block small">{{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalCalls']) }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="rounded border bg-body p-2 text-center">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Ahorro</small>
                        <span class="fw-semibold text-success d-block small">{{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalTokensSaved']) }}</span>
                    </div>
                </div>
            </div>

            @if (! empty($tokenStats['byModule']))
                <div class="mb-2" id="tokensByModuleChart" data-chart-height="160"></div>
            @endif

            <div class="border-top pt-2 mt-2">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <small class="text-muted" style="font-size: 0.7rem;">Tokens ahorrados</small>
                    <span class="badge bg-label-secondary" style="font-size: 0.65rem;">{{ $tokenStats['averageSavings'] }}%</span>
                </div>
                <div class="progress mb-1" style="height: 4px;">
                    <div class="progress-bar bg-secondary"
                         role="progressbar"
                         style="width: {{ $tokenStats['averageSavings'] }}%;"
                         aria-valuenow="{{ $tokenStats['averageSavings'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="text-muted" style="font-size: 0.65rem;">Usados: {{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalTokensUsed']) }}</small>
                    <small class="text-muted" style="font-size: 0.65rem;">Sin optimización: {{ \App\Helpers\Helpers::formatCompactNumber($tokenStats['totalTokensWithoutToon']) }}</small>
                </div>
            </div>
        </div>
    </div>
@endif
