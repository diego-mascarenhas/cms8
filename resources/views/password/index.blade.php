@extends('layouts/layoutMaster')

@section('title', 'Passwords')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Passwords</h4>
        <p class="text-muted">{{ __('Secure team vault with one-time public share links.') }}</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <form method="POST" action="{{ route('passwords.lock') }}">
            @csrf
            <button class="btn btn-outline-secondary" type="submit">
                <i class="ti ti-lock me-1"></i>Lock vault
            </button>
        </form>
        @can('create', \App\Models\TeamPassword::class)
            <a href="{{ route('passwords.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Add password
            </a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card mb-4">
    <div class="card-header border-bottom">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="filter_password_enterprise_id">{{ __('Enterprise') }}</label>
                <select id="filter_password_enterprise_id" class="form-select">
                    <option value="">{{ __('All') }}</option>
                    @foreach($enterprises as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
<script>
$(function () {
    $(document).on('change', '#filter_password_enterprise_id', function () {
        var table = window.LaravelDataTables?.['team-passwords-table'];
        if (table) {
            table.ajax.reload();
        }
    });
});

async function revealPassword(passwordId) {
    let response = await fetch(`/password/${passwordId}/reveal`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    let payload = await response.json();
    if (response.status === 423 && payload.requires_unlock) {
        const unlocked = await unlockVaultInline();
        if (!unlocked) return;
        response = await fetch(`/password/${passwordId}/reveal`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        payload = await response.json();
    }

    if (!response.ok || !payload.password) {
        alert(payload.message || 'Unable to reveal password');
        return;
    }

    await navigator.clipboard.writeText(payload.password);
    alert('Password copied to clipboard');
}

async function createShare(passwordId) {
    let response = await fetch(`/password/${passwordId}/share`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    let payload = await response.json();
    if (response.status === 423 && payload.requires_unlock) {
        const unlocked = await unlockVaultInline();
        if (!unlocked) return;
        response = await fetch(`/password/${passwordId}/share`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        payload = await response.json();
    }

    if (!response.ok || !payload.url) {
        alert(payload.message || 'Unable to generate share URL');
        return;
    }

    await navigator.clipboard.writeText(payload.url);
    alert('Public one-time URL copied. You can send it by email or WhatsApp.');
}

async function unlockVaultInline() {
    const masterKey = window.prompt('Enter your master key to continue');
    if (!masterKey) return false;

    const response = await fetch(`{{ route('passwords.unlock') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ master_key: masterKey })
    });

    if (!response.ok) {
        const payload = await response.json();
        alert(payload.message || 'Invalid master key');
        return false;
    }

    return true;
}
</script>
@endsection
