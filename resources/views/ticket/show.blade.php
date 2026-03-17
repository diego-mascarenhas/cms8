@extends('layouts/layoutMaster')

@section('title', __('tickets.Ticket') . ' #' . $ticket->id)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('tickets.Tickets') }}/</span> {{ $ticket->subject }}</h4>
        <p class="text-muted">{{ __('tickets.Ticket') }} #{{ $ticket->id }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2">
        <a href="{{ route('ticket.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i> {{ __('tickets.Back to list') }}
        </a>
        @can('update', $ticket)
        @if ($ticket->isOpen())
        <form action="{{ route('ticket.close', $ticket->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning">{{ __('tickets.Close ticket') }}</button>
        </form>
        @endif
        @endcan
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

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('tickets.Details') }}</h5>
                <span class="badge bg-{{ $ticket->status_color }}">{{ $ticket->status_label }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">{{ __('tickets.Priority') }}</small>
                        <span class="badge bg-{{ $ticket->priority_color }}">{{ $ticket->priority_label }}</span>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">{{ __('tickets.Created') }}</small>
                        {{ $ticket->created_at->format('d/m/Y H:i') }}
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">{{ __('tickets.Created by') }}</small>
                        {{ $ticket->user?->name ?? '—' }}
                    </div>
                    @if ($ticket->assignedTo)
                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block">{{ __('tickets.Assigned to') }}</small>
                        {{ $ticket->assignedTo->name }}
                    </div>
                    @endif
                </div>
                <hr>
                <h6 class="mb-2">{{ __('tickets.Description') }}</h6>
                <p class="mb-0 text-body" style="white-space: pre-wrap;">{{ $ticket->description }}</p>

                @if ($ticket->getMedia('attachments')->count() > 0)
                <hr>
                <h6 class="mb-2">{{ __('tickets.Attachments') }}</h6>
                <ul class="list-unstyled mb-0">
                    @foreach ($ticket->getMedia('attachments') as $media)
                    <li>
                        <a href="{{ route('ticket.attachment', [$ticket->id, $media->id]) }}" target="_blank" class="text-body">
                            <i class="ti ti-file me-1"></i> {{ $media->file_name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <h5 class="card-header">{{ __('tickets.Conversation') }}</h5>
            <div class="card-body">
                @forelse ($ticket->responses as $response)
                    @if ($response->is_internal_note && !auth()->user()->hasRole('admin'))
                        @continue
                    @endif
                    <div class="d-flex gap-3 mb-4 pb-3 border-bottom">
                        <div class="flex-shrink-0">
                            <span class="avatar avatar-sm bg-label-{{ $response->user_id === $ticket->user_id ? 'primary' : ($response->is_internal_note ? 'warning' : 'success') }} rounded">
                                {{ strtoupper(substr($response->user?->name ?? '?', 0, 1)) }}
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <h6 class="mb-0">
                                    {{ $response->user?->name ?? '—' }}
                                    @if ($response->is_internal_note)
                                        <span class="badge bg-warning ms-1">{{ __('tickets.Internal note') }}</span>
                                    @elseif ($response->user_id !== $ticket->user_id)
                                        <span class="badge bg-success ms-1">{{ __('tickets.Support') }}</span>
                                    @else
                                        <span class="badge bg-primary ms-1">{{ __('tickets.Client') }}</span>
                                    @endif
                                </h6>
                                <small class="text-muted">{{ $response->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                            <p class="mt-1 mb-0 text-body" style="white-space: pre-wrap;">{{ $response->message }}</p>
                            @if ($response->getMedia('attachments')->count() > 0)
                            <div class="mt-2">
                                @foreach ($response->getMedia('attachments') as $media)
                                <a href="{{ route('ticket.attachment', [$ticket->id, $media->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary me-1">
                                    <i class="ti ti-download ti-xs"></i> {{ $media->file_name }}
                                </a>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center py-4 mb-0">{{ __('tickets.No responses yet') }}</p>
                @endforelse

                @if ($ticket->isOpen())
                <hr>
                <h6 class="mb-3">{{ __('tickets.Add reply') }}</h6>
                <form action="{{ route('ticket.response', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="4" required placeholder="{{ __('tickets.Your message') }}">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if (auth()->user()->hasRole('admin'))
                    <div class="form-check mb-3">
                        <input type="hidden" name="is_internal_note" value="0">
                        <input class="form-check-input" type="checkbox" name="is_internal_note" id="is_internal_note" value="1" {{ old('is_internal_note') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_internal_note">{{ __('tickets.Internal note (not visible to client)') }}</label>
                    </div>
                    @endif
                    <div class="mb-3">
                        <input type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.txt,.doc,.docx">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('tickets.Send reply') }}</button>
                </form>
                @endif
            </div>
        </div>

        @if ($ticket->isClosed() && !$ticket->rating && $ticket->user_id === auth()->id())
        <div class="card mb-4">
            <h5 class="card-header">{{ __('tickets.Rate this ticket') }}</h5>
            <div class="card-body">
                <form action="{{ route('ticket.rate', $ticket->id) }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('tickets.Response time') }} (1-5)</label>
                            <select name="tiempo_respuesta" class="form-select" required>
                                @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}" {{ old('tiempo_respuesta') == $i ? 'selected' : '' }}>{{ $i }}</option>@endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('tickets.Attention') }} (1-5)</label>
                            <select name="atencion" class="form-select" required>
                                @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}" {{ old('atencion') == $i ? 'selected' : '' }}>{{ $i }}</option>@endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('tickets.Solution') }} (1-5)</label>
                            <select name="solucion" class="form-select" required>
                                @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}" {{ old('solucion') == $i ? 'selected' : '' }}>{{ $i }}</option>@endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('tickets.Comments') }}</label>
                            <textarea name="comentarios" class="form-control" rows="2" maxlength="1000">{{ old('comentarios') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">{{ __('tickets.Submit rating') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif

        @if ($ticket->rating)
        <div class="card mb-4">
            <h5 class="card-header">{{ __('tickets.Rating') }}</h5>
            <div class="card-body">
                <p class="mb-0">{{ __('tickets.Average') }}: {{ $ticket->rating->promedio }}/5. @if ($ticket->rating->comentarios){{ $ticket->rating->comentarios }}@endif</p>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        @can('update', $ticket)
        <div class="card mb-4">
            <h5 class="card-header">{{ __('tickets.Actions') }}</h5>
            <div class="card-body">
                <form action="{{ route('ticket.status', $ticket->id) }}" method="POST" class="mb-3">
                    @csrf
                    <label class="form-label">{{ __('tickets.Status') }}</label>
                    <div class="input-group">
                        <select name="status" class="form-select">
                            @foreach (['open' => __('tickets.Open'), 'in_progress' => __('tickets.In Progress'), 'waiting_client' => __('tickets.Waiting Client'), 'closed' => __('tickets.Closed')] as $val => $label)
                            <option value="{{ $val }}" {{ $ticket->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-primary">{{ __('tickets.Update') }}</button>
                    </div>
                </form>

                <form action="{{ route('ticket.assign', $ticket->id) }}" method="POST" class="mb-3">
                    @csrf
                    <label class="form-label">{{ __('tickets.Assign to') }}</label>
                    <div class="input-group">
                        <select name="assigned_to" class="form-select">
                            <option value="">{{ __('tickets.Unassigned') }}</option>
                            @foreach ($teamUsers as $u)
                            <option value="{{ $u->id }}" {{ $ticket->assigned_to === $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-primary">{{ __('tickets.Assign') }}</button>
                    </div>
                </form>

                <form action="{{ route('ticket.priority', $ticket->id) }}" method="POST">
                    @csrf
                    <label class="form-label">{{ __('tickets.Priority') }}</label>
                    <div class="input-group">
                        <select name="priority" class="form-select">
                            @foreach (['low' => __('tickets.Low'), 'medium' => __('tickets.Medium'), 'high' => __('tickets.High'), 'urgent' => __('tickets.Urgent')] as $val => $label)
                            <option value="{{ $val }}" {{ $ticket->priority === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-primary">{{ __('tickets.Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
