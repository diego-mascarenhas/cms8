<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-1">Horas y sueldos</h5>
        <p class="text-muted mb-0">Coste de carga = sueldo / horas de capacidad × horas asignadas en el mapa. Sueldos en “A definir” hasta que se cierren.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Rol</th>
                    <th class="text-end">Capacidad / mes</th>
                    <th class="text-end">Horas asignadas</th>
                    <th class="text-end">Sueldo</th>
                    <th class="text-end">Coste de carga</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($catalog['cost_rows'] as $row)
                    @if ($row['person']['kind'] === 'internal')
                        <tr>
                            <td>{{ $row['person']['name'] }}</td>
                            <td>{{ implode(' · ', $row['person']['titles']) }}</td>
                            <td class="text-end">{{ $row['capacity_hours'] !== null ? $row['capacity_hours'].' h' : '—' }}</td>
                            <td class="text-end">{{ $row['assigned_hours'] }} h</td>
                            <td class="text-end">{{ $row['salary'] !== null ? number_format($row['salary'], 2, ',', '.').' €' : 'A definir' }}</td>
                            <td class="text-end">{{ $row['load_cost'] !== null ? number_format($row['load_cost'], 2, ',', '.').' €' : 'A definir' }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
