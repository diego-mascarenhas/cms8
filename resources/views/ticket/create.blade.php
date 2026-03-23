@extends('layouts/layoutMaster')

@section('title', __('tickets.New ticket'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('tickets.Tickets') }}/</span> {{ __('tickets.Create') }}</h4>
        <p class="text-muted">{{ __('tickets.Open a new support ticket') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('ticket.index') }}" class="btn btn-label-secondary">{{ __('tickets.Cancel') }}</a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('tickets.Ticket details') }}</h5>
    <form class="card-body" action="{{ route('ticket.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
            <div class="col-12">
                <label for="subject" class="form-label">{{ __('tickets.Subject') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="255">
                @error('subject')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="priority" class="form-label">{{ __('tickets.Priority') }} <span class="text-danger">*</span></label>
                <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority" required>
                    <option value="low" {{ old('priority', 'medium') === 'low' ? 'selected' : '' }}>{{ __('tickets.Low') }}</option>
                    <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('tickets.Medium') }}</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('tickets.High') }}</option>
                    <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ __('tickets.Urgent') }}</option>
                </select>
                @error('priority')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="description" class="form-label">{{ __('tickets.Description') }} <span class="text-danger">*</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="attachments" class="form-label">{{ __('tickets.Attachments') }}</label>
                <input type="file" class="form-control @error('attachments.*') is-invalid @enderror" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.txt,.doc,.docx">
                <small class="text-muted">{{ __('tickets.Max 10 MB per file. Allowed: images, PDF, ZIP, TXT, DOC, DOCX') }}</small>
                @error('attachments.*')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-2">{{ __('tickets.Create ticket') }}</button>
            <a href="{{ route('ticket.index') }}" class="btn btn-label-secondary">{{ __('tickets.Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
