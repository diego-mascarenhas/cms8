@extends('layouts/layoutMaster')

@section('title', $meeting->title)

@if (in_array($meeting->status->value, ['pending', 'processing'], true))
    @section('page-script')
        <script>
            setTimeout(function () { window.location.reload(); }, 8000);
        </script>
    @endsection
@endif

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Reuniones /</span> {{ $meeting->title }}</h4>
        <p class="text-muted">{{ $meeting->original_filename }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('meeting.index') }}" class="btn btn-label-secondary">Volver</a>
        <form action="{{ route('meeting.destroy', $meeting) }}" method="POST" onsubmit="return confirm('¿Eliminar esta reunión?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-label-danger">Eliminar</button>
        </form>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($meeting->status->value === 'pending' || $meeting->status->value === 'processing')
    <div class="alert alert-info">Se está transcribiendo el audio y separando las voces. Esta página se actualiza sola.</div>
@endif

@if ($meeting->status->value === 'failed')
    <div class="alert alert-danger">No se pudo transcribir. {{ $meeting->error }}</div>
@endif

@if ($meeting->status->value === 'ready')
    <div class="card">
        <div class="card-body d-flex flex-column gap-4">
            @forelse ($meeting->turns ?? [] as $turn)
                <div>
                    <div class="d-flex justify-content-between">
                        <strong>{{ $turn['label'] ?? 'Persona' }}</strong>
                        <span class="text-muted">{{ gmdate('i:s', (int) ($turn['start_seconds'] ?? 0)) }}</span>
                    </div>
                    <p class="mb-0">{{ $turn['text'] ?? '' }}</p>
                </div>
            @empty
                <p class="text-muted mb-0">No hay intervenciones para mostrar.</p>
            @endforelse
        </div>
    </div>
@endif
@endsection
