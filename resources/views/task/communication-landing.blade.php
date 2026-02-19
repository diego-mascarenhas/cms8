@extends('layouts/layoutClientLanding')

@section('title', __('Project communication') . ' - ' . config('app.name'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="ti ti-message-circle me-2"></i>{{ __('Project communication') }}
                </h4>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <i class="ti ti-check me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="border-start border-primary border-4 rounded-end p-3 bg-body-secondary">
                            <p class="mb-1"><strong>{{ __('Enterprise') }}:</strong> {{ $project->enterprise->name ?? '—' }}</p>
                            <p class="mb-1"><strong>{{ __('Project') }}:</strong> {{ $project->name }}</p>
                            <p class="mb-0"><strong>{{ __('Task') }}:</strong> {{ $communication->task->title }}</p>
                            <p class="mb-0 mt-1 small text-muted">{{ __('Sent on') }}: {{ $communication->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="ti ti-message me-1"></i>{{ __('Message from') }} {{ $communication->user->name ?? __('System') }}</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $communication->message }}</p>
                    </div>
                </div>

                @if($communication->response)
                    <div class="alert alert-success mb-4">
                        <h6 class="alert-heading"><i class="ti ti-check-circle me-1"></i>{{ __('Your response') }}</h6>
                        <p class="mb-1">{{ $communication->response }}</p>
                        <small class="text-muted">{{ __('Answered on') }} {{ $communication->response_at->format('d/m/Y H:i') }}</small>
                    </div>
                @else
                    <div class="card mb-4">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="ti ti-pencil me-1"></i>{{ __('Your response') }}</h6>
                        </div>
                        <div class="card-body">
                            <form id="communication-form" action="{{ route('task.communication.respond.store', $communication->response_token) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="response" class="form-label">{{ __('Write your response') }}</label>
                                    <textarea
                                        name="response"
                                        id="response"
                                        class="form-control"
                                        rows="4"
                                        placeholder="{{ __('Write your response here...') }}"
                                        required
                                    >{{ old('response') }}</textarea>
                                    @error('response')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" name="action" value="respond_todo" class="btn btn-primary">
                                        <i class="ti ti-list me-1"></i>{{ __('Reply and set to To Do') }}
                                    </button>
                                    <button type="submit" name="action" value="mark_complete" class="btn btn-success">
                                        <i class="ti ti-circle-check me-1"></i>{{ __('Mark task as completed') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <h6 class="mb-2">{{ __('Project tasks') }}</h6>
                <p class="text-muted small mb-3">{{ __('Tasks linked to this project. No activity or time data is shown.') }}</p>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('Task') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tasks as $t)
                                <tr>
                                    <td>{{ $t->title }}</td>
                                    <td><span class="badge bg-label-secondary">{{ $t->status ? __('task_status.' . $t->status->name) : '—' }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-muted">{{ __('No tasks') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
