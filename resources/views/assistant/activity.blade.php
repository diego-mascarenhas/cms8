@extends('layouts/layoutMaster')

@section('title', 'AI Activity')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">AI Activity</h4>
        <p class="text-muted">Team assistant conversations, token usage, model and estimated costs</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('assistant') }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-message-circle me-1"></i>Open Assistant
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Assistant messages</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalMessages) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Total tokens</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalTokens) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Estimated cost (USD)</small>
                <h4 class="mb-0">${{ number_format($totalEstimatedCostUsd, 6) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Conversation messages</h5>
            <small class="text-muted">Default provider/model: {{ $defaultProvider }} / {{ $defaultModel }}</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Conversation</th>
                    <th>Model</th>
                    <th class="text-end">Prompt</th>
                    <th class="text-end">Completion</th>
                    <th class="text-end">Total tokens</th>
                    <th class="text-end">Estimated USD</th>
                </tr>
            </thead>
            <tbody>
                @forelse($messages as $message)
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                <small class="text-muted">{{ $message->created_at?->diffForHumans() }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->conversation?->user?->name ?? $message->user?->name ?? 'Unknown' }}</span>
                                <small class="text-muted">{{ $message->conversation?->user?->email ?? $message->user?->email }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->conversation?->title ?? 'Untitled' }}</span>
                                <small class="text-muted">{{ $message->conversation_id }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->model_name }}</span>
                                <small class="text-muted">{{ $message->provider_name }}</small>
                            </div>
                        </td>
                        <td class="text-end">{{ number_format((int) $message->prompt_tokens_value) }}</td>
                        <td class="text-end">{{ number_format((int) $message->completion_tokens_value) }}</td>
                        <td class="text-end">{{ number_format((int) $message->total_tokens_value) }}</td>
                        <td class="text-end">${{ number_format((float) $message->estimated_cost_usd, 6) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No assistant activity found for this team.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($messages->hasPages())
        <div class="card-body border-top">
            {{ $messages->links() }}
        </div>
    @endif
</div>
@endsection
