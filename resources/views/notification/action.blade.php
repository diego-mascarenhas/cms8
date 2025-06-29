<div class="d-flex justify-content-center align-items-center">
    <!-- View -->
    <a href="{{ route('notification.show', $notification->id) }}" class="text-body me-2" title="Ver detalles">
        <i class="ti ti-eye ti-sm"></i>
    </a>

    @if (!$notification->is_sent)
        <!-- Send -->
        <a href="javascript:;" class="text-success me-2" title="Enviar notificación" 
           onclick="sendNotification({{ $notification->id }})">
            <i class="ti ti-send ti-sm"></i>
        </a>

        <!-- Edit -->
        <a href="{{ route('notification.edit', $notification->id) }}" class="text-primary me-2" title="Editar">
            <i class="ti ti-edit ti-sm"></i>
        </a>
    @else
        <!-- Resend -->
        <a href="javascript:;" class="text-warning me-2" title="Reenviar notificación" 
           onclick="resendNotification({{ $notification->id }})">
            <i class="ti ti-refresh ti-sm"></i>
        </a>
    @endif

    <!-- Delete -->
    <form action="{{ route('notification.destroy', $notification->id) }}" method="POST" class="d-inline">
        @csrf
        @method('DELETE')
        <a href="javascript:;" class="text-danger" title="Eliminar" 
           onclick="deleteRecord({{ $notification->id }}, this)">
            <i class="ti ti-trash ti-sm"></i>
        </a>
    </form>
</div> 