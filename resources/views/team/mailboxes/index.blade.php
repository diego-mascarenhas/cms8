@extends('layouts/layoutMaster')

@section('title', __('Team Mailboxes'))

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Settings') }}/</span> {{ __('Casillas de correo') }}</h4>
        <p class="text-muted">{{ __('Manage the shared company mailbox and your personal IMAP mailboxes') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Settings') }}
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

{{-- Team / company mailboxes --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
    <div>
        <h5 class="mb-0">{{ __('Casillas del equipo') }}</h5>
        <small class="text-muted">{{ __('Shared company inbox — common to the whole team') }}</small>
    </div>
    <a href="{{ route('team.mailboxes.create', $team) }}" class="btn btn-primary btn-sm mt-2 mt-md-0">
        <i class="ti ti-plus me-1"></i>{{ __('Añadir casilla') }}
    </a>
</div>

<div class="card mb-4">
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
                @forelse($teamMailboxes as $mailbox)
                    @include('team.mailboxes.partials.row', ['team' => $team, 'mailbox' => $mailbox])
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

{{-- Personal mailboxes --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
    <div>
        <h5 class="mb-0">{{ __('Mis casillas personales') }}</h5>
        <small class="text-muted">{{ __('Your personal IMAP accounts for performance insights and follow-up') }}</small>
    </div>
    <a href="{{ route('team.mailboxes.create', [$team, 'ownership' => 'personal']) }}" class="btn btn-primary btn-sm mt-2 mt-md-0">
        <i class="ti ti-plus me-1"></i>{{ __('Añadir casilla personal') }}
    </a>
</div>

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
                @forelse($personalMailboxes as $mailbox)
                    @include('team.mailboxes.partials.row', ['team' => $team, 'mailbox' => $mailbox])
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="ti ti-user-off mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">{{ __('No tienes casillas personales') }}</p>
                            <small>{{ __('Añade tu email personal (IMAP) igual que la casilla de la empresa') }}</small>
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
