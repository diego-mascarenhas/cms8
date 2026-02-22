@extends('layouts/layoutMaster')

@section('title', __('Team Mailboxes'))

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Settings') }}/</span> {{ __('Casillas del equipo') }}</h4>
        <p class="text-muted">{{ __('Manage IMAP mailboxes for your team') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Settings') }}
        </a>
        <a href="{{ route('team.mailboxes.create', $team) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>{{ __('Añadir casilla') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Host') }}</th>
                    <th>{{ __('User') }}</th>
                    <th class="text-center">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mailboxes as $mailbox)
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
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="ti ti-mail-off mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">{{ __('No hay casillas configuradas') }}</p>
                            <small>{{ __('Haz clic en "Añadir casilla" para crear la primera') }}</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('page-script')
<script>
    function testMailboxConnection(teamId, mailboxId, button) {
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>{{ __("Probando...") }}';

        fetch(`/team/${teamId}/mailboxes/${mailboxId}/test-connection`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>{{ __("Success!") }}';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>{{ __("Failed") }}';
            }
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalHtml;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>{{ __("Error") }}';
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalHtml;
            }, 3000);
        });
    }
</script>
@endsection
