@extends('layouts/layoutMaster')

@section('title', 'AI Activity')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
    <script>
        $(function () {
            $('#assistant-activity-table').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    emptyTable: 'No assistant activity found for this range.'
                }
            });
        });
    </script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">AI Activity</h4>
        <p class="text-muted">Team assistant conversations, token usage, model and estimated costs</p>
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
        <form method="GET" action="{{ route('assistant.activity') }}" class="d-flex align-items-end gap-2">
            <div>
                <label for="start_date" class="form-label mb-1">From</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>
            <div>
                <label for="end_date" class="form-label mb-1">To</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary waves-effect waves-light">Apply</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table id="assistant-activity-table" class="table table-hover">
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
</div>
@endsection
