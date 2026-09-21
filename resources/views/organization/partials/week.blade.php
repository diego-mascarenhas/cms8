<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-1">Semana por persona</h5>
        <p class="text-muted mb-0">Solo operaciones con día fijo. Bloqueos personales (gris) no cuentan horas. Teléfono y WhatsApp bajo demanda no ocupan celda.</p>
    </div>
    <div class="card-body">
        @foreach ($catalog['week_by_person'] as $week)
            @if ($week['person']['kind'] === 'internal')
                <h6 class="mt-3 mb-2 {{ $week['is_current'] ? 'text-primary' : '' }}">
                    {{ $week['person']['name'] }}
                    @if ($week['person']['work_starts_at'])
                        <span class="text-muted fw-normal">· {{ $week['person']['work_starts_at'] }}–{{ $week['person']['work_ends_at'] }}</span>
                    @endif
                </h6>
                @if (count($week['grid']['hours']) === 0)
                    <p class="text-muted small">Sin operación fija esta semana.</p>
                @else
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm organization-week-grid mb-0">
                        <thead>
                            <tr>
                                <th style="width: 4.5rem;">Hora</th>
                                @foreach ($catalog['weekday_labels'] as $weekday => $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($week['grid']['hours'] as $hour)
                                <tr>
                                    <td class="text-muted small">{{ $hour }}</td>
                                    @foreach ($catalog['weekday_labels'] as $weekday => $label)
                                        @php
                                            $blocks = array_values(array_filter(
                                                $week['grid']['days'][$weekday] ?? [],
                                                fn ($block) => \App\Support\RevisionAlphaOrganization::hourOverlapsBlock($hour, $block['starts_at'], $block['ends_at'])
                                            ));
                                        @endphp
                                        <td class="small p-1">
                                            @foreach ($blocks as $block)
                                                <div class="organization-week-block mb-1 {{ !empty($block['is_blocker']) ? 'organization-week-blocker' : '' }}" style="background: {{ $block['color'] }};">
                                                    {{ $block['name'] }}
                                                    @if (!empty($block['is_blocker']))
                                                        <span class="d-block text-muted">Agenda · no laboral</span>
                                                    @endif
                                                    <span class="d-block text-muted">{{ $block['starts_at'] }}–{{ $block['ends_at'] }}</span>
                                                </div>
                                            @endforeach
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                @if (count($week['processes']))
                    <div class="mb-4">
                        <p class="small text-muted mb-2">Tareas de {{ $week['person']['name'] }} (paso a paso)</p>
                        <div class="row g-3">
                            @foreach ($week['processes'] as $process)
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between gap-2">
                                            <strong>{{ $process['name'] }}</strong>
                                            <span class="badge bg-label-secondary">{{ $catalog['departments'][$process['department']]['name'] }}</span>
                                        </div>
                                        <p class="small text-muted mb-2">{{ $process['time_allocation'] }} · {{ $process['company']['name'] ?? '' }}</p>
                                        <ol class="small mb-0 ps-3">
                                            @foreach ($process['steps'] as $step)
                                                <li>{{ $step }}</li>
                                            @endforeach
                                        </ol>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        @endforeach
    </div>
</div>
