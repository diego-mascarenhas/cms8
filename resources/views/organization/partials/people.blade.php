<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-1">Personas</h5>
        <p class="text-muted mb-0">Rol en el holding. El sueldo se completa cuando esté cerrado; hasta entonces figura como “A definir”.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ($catalog['people'] as $person)
                <div class="col-md-6 col-xl-4" id="person-{{ $person['key'] }}">
                    <div class="border rounded p-3 h-100 {{ $catalog['current_person_key'] === $person['key'] ? 'border-primary' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h6 class="mb-1">{{ $person['name'] }}</h6>
                            <span class="badge {{ $person['kind'] === 'internal' ? 'bg-label-primary' : 'bg-label-secondary' }}">
                                {{ $person['kind'] === 'internal' ? 'Equipo' : 'Alianza' }}
                            </span>
                        </div>
                        <ul class="list-unstyled small mb-2">
                            @foreach ($person['titles'] as $title)
                                <li>{{ $title }}</li>
                            @endforeach
                        </ul>
                        <p class="small mb-2">{{ $person['summary'] }}</p>
                        @if ($person['work_starts_at'] && $person['work_ends_at'])
                            <p class="small text-muted mb-0">
                                Ventana: {{ $person['work_starts_at'] }}–{{ $person['work_ends_at'] }}
                                @if ($person['weekly_hours'])
                                    · {{ $person['weekly_hours'] }} h/semana
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
