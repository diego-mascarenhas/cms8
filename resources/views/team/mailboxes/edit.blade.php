@extends('layouts/layoutMaster')

@section('title', __('Edit Mailbox'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Settings') }}/</span> {{ __('Editar casilla') }}</h4>
        <p class="text-muted">{{ $mailbox->name }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('team.mailboxes.index', $team) }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('team.mailboxes.update', [$team, $mailbox]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">{{ __('Name') }} (*)</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $mailbox->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="folder" class="form-label">{{ __('Folder') }}</label>
                    <input type="text" class="form-control @error('folder') is-invalid @enderror" id="folder" name="folder" value="{{ old('folder', $mailbox->folder) }}" placeholder="INBOX">
                    @error('folder')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="host" class="form-label">{{ __('Host') }} (*)</label>
                    <input type="text" class="form-control @error('host') is-invalid @enderror" id="host" name="host" value="{{ old('host', $mailbox->host) }}" required>
                    @error('host')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="port" class="form-label">{{ __('Port') }} (*)</label>
                    <input type="number" class="form-control @error('port') is-invalid @enderror" id="port" name="port" value="{{ old('port', $mailbox->port) }}" min="1" max="65535" required>
                    @error('port')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="encryption" class="form-label">{{ __('Encryption') }}</label>
                    <select class="form-select @error('encryption') is-invalid @enderror" id="encryption" name="encryption">
                        <option value="ssl" {{ old('encryption', $mailbox->encryption) === 'ssl' ? 'selected' : '' }}>SSL</option>
                        <option value="tls" {{ old('encryption', $mailbox->encryption) === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="none" {{ old('encryption', $mailbox->encryption) === 'none' ? 'selected' : '' }}>{{ __('None') }}</option>
                    </select>
                    @error('encryption')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="username" class="form-label">{{ __('Username') }} (*)</label>
                    <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $mailbox->username) }}" required>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="{{ __('Dejar en blanco para no cambiar') }}">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="protocol" class="form-label">{{ __('Protocol') }}</label>
                    <select class="form-select @error('protocol') is-invalid @enderror" id="protocol" name="protocol">
                        <option value="imap" {{ old('protocol', $mailbox->protocol) === 'imap' ? 'selected' : '' }}>IMAP</option>
                        <option value="imap2" {{ old('protocol', $mailbox->protocol) === 'imap2' ? 'selected' : '' }}>IMAP2</option>
                    </select>
                    @error('protocol')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary me-2">{{ __('Save') }}</button>
                <a href="{{ route('team.mailboxes.index', $team) }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
