<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-1">Guardia 24×7 · huecos de cobertura</h5>
        <p class="text-muted mb-0">
            Quién está frente a la computadora en cada hora. Los bloqueos personales (inglés, Master Mind) no cubren.
            Huecos de la semana: <strong>{{ (int) $catalog['coverage_grid']['gap_hours_per_week'] }}</strong> h.
        </p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm organization-week-grid organization-coverage-grid mb-0">
                <thead>
                    <tr>
                        <th style="width: 4.5rem;">Hora</th>
                        @foreach ($catalog['weekday_labels'] as $weekday => $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($catalog['coverage_grid']['hours'] as $hour)
                        <tr>
                            <td class="text-muted small">{{ $hour }}</td>
                            @foreach ($catalog['weekday_labels'] as $weekday => $label)
                                @php
                                    $slot = $catalog['coverage_grid']['days'][$weekday][$hour];
                                @endphp
                                <td class="small p-1 {{ $slot['covered'] ? '' : 'organization-coverage-gap' }}">
                                    @if ($slot['covered'])
                                        @foreach ($slot['covers'] as $cover)
                                            <div class="organization-week-block mb-1" style="background: {{ $cover['color'] }};">
                                                {{ $cover['person'] }}
                                                <span class="d-block text-muted">{{ $cover['name'] }}</span>
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="organization-coverage-gap-label">Sin cobertura</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
