<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('meeting.show', $meeting) }}" class="text-body" title="Ver">
        <i class="ti ti-edit ti-sm me-2"></i>
    </a>
    <a href="#" class="text-danger" title="Eliminar" onclick="event.preventDefault(); if (confirm('¿Eliminar esta reunión?')) { document.getElementById('delete-meeting-{{ $meeting->id }}').submit(); }">
        <i class="ti ti-trash ti-sm"></i>
    </a>
    <form id="delete-meeting-{{ $meeting->id }}" action="{{ route('meeting.destroy', $meeting) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
</div>
