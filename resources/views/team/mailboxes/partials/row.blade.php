<tr>
    <td>{{ $mailbox->name }}</td>
    <td>{{ $mailbox->host }}:{{ $mailbox->port }}</td>
    <td>{{ $mailbox->username }}</td>
    <td>
        <div class="d-flex justify-content-center align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-info" onclick="testMailboxConnection({{ $team->id }}, {{ $mailbox->id }}, this)">
                <i class="ti ti-plug me-1"></i>{{ __('Probar conexión') }}
            </button>
            <a href="{{ route('team.mailboxes.edit', [$team, $mailbox]) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-edit me-1"></i>{{ __('Editar') }}
            </a>
            <form action="{{ route('team.mailboxes.destroy', [$team, $mailbox]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('¿Eliminar esta casilla?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="ti ti-trash me-1"></i>{{ __('Eliminar') }}
                </button>
            </form>
        </div>
    </td>
</tr>
