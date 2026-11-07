@php
    $minutes = $summary['minutes'] ?? $summary['synthesis'] ?? '';
    $points = is_array($summary['points'] ?? null) ? $summary['points'] : [];
    $conclusions = is_array($summary['conclusions'] ?? null) ? $summary['conclusions'] : [];
    if ($conclusions === [] && is_array($summary['tasks'] ?? null)) {
        foreach ($summary['tasks'] as $task) {
            if (! is_array($task)) {
                continue;
            }
            $conclusions[] = [
                'title' => $task['title'] ?? '',
                'owner' => $task['owner'] ?? '',
                'detail' => '',
            ];
        }
    }
    $opinions = is_array($summary['opinions'] ?? null) && $summary['opinions'] !== []
        ? $summary['opinions']
        : (is_array($summary['viewpoints'] ?? null) ? $summary['viewpoints'] : []);
    $opinion = $summary['opinion'] ?? '';
    $followUp = is_array($summary['follow_up'] ?? null) ? $summary['follow_up'] : [];
@endphp

@if ($minutes !== '')
    <h6 class="mb-2">Síntesis</h6>
    <p class="mb-3">{{ $minutes }}</p>
@endif

@if ($points !== [])
    <h6 class="mb-2">Puntos tratados</h6>
    <ul class="mb-3">
        @foreach ($points as $point)
            <li class="mb-2">
                <strong>{{ $point['topic'] ?? '' }}</strong>
                @if (($point['agree'] ?? []) !== [])
                    <div>De acuerdo: {{ implode(', ', $point['agree']) }}</div>
                @endif
                @if (($point['disagree'] ?? []) !== [])
                    <div>En desacuerdo: {{ implode(', ', $point['disagree']) }}</div>
                @endif
                @if (($point['agree'] ?? []) === [] && ($point['disagree'] ?? []) === [])
                    <div class="text-muted">No quedó dicho quién está de acuerdo ni quién no.</div>
                @endif
            </li>
        @endforeach
    </ul>
@endif

@if ($conclusions !== [])
    <h6 class="mb-2">Conclusiones</h6>
    <ul class="mb-3">
        @foreach ($conclusions as $conclusion)
            @if (($conclusion['title'] ?? '') === '')
                @continue
            @endif
            <li class="mb-2">
                @if (! empty($conclusion['task_id']) && auth()->check())
                    <a href="{{ route('task.show', $conclusion['task_id']) }}">{{ $conclusion['title'] }}</a>
                @else
                    {{ $conclusion['title'] }}
                @endif
                @if (($conclusion['owner'] ?? '') !== '')
                    <span class="text-muted">— {{ $conclusion['owner'] }}</span>
                @endif
                @if (($conclusion['detail'] ?? '') !== '')
                    <div>{{ $conclusion['detail'] }}</div>
                @endif
            </li>
        @endforeach
    </ul>
@endif

@if ($opinions !== [])
    <h6 class="mb-2">Opinión</h6>
    <ul class="mb-3">
        @foreach ($opinions as $row)
            <li>
                @if (($row['speaker'] ?? '') !== '')
                    <strong>{{ $row['speaker'] }}.</strong>
                @endif
                {{ $row['text'] ?? $row['view'] ?? '' }}
            </li>
        @endforeach
    </ul>
@endif

@if ($opinion !== '')
    <h6 class="mb-2">Recomendación</h6>
    <p class="text-muted mb-1">A partir de lo conversado. No es algo que se haya dicho en la reunión.</p>
    <p class="mb-3">{{ $opinion }}</p>
@endif

@if (($followUp['reason'] ?? '') !== '' || ($followUp['after'] ?? []) !== [] || ($followUp['suggest'] ?? false))
    <h6 class="mb-2">Próxima reunión</h6>
    <p class="mb-2">{{ ($followUp['suggest'] ?? false) ? 'Conviene otra reunión después de tratar estos puntos.' : 'No hace falta otra reunión por ahora.' }}</p>
    @if (($followUp['reason'] ?? '') !== '')
        <p class="mb-2">{{ $followUp['reason'] }}</p>
    @endif
    @if (($followUp['after'] ?? []) !== [])
        <p class="mb-1">Antes:</p>
        <ul class="mb-3">
            @foreach ($followUp['after'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif
@endif

@if (($summary['intention'] ?? '') !== '')
    <h6 class="mb-2">Intención</h6>
    <p class="mb-3">{{ $summary['intention'] }}</p>
@endif

@if (($summary['items'] ?? []) !== [])
    <h6 class="mb-2">Para tratar</h6>
    <ol class="mb-0">
        @foreach ($summary['items'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>
@endif
