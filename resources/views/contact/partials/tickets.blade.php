<div class="card mb-4">
    <h5 class="card-header">{{ __('tickets.Tickets') }}</h5>
    <div class="card-body">
        @if ($contactTickets->isEmpty())
            <p class="text-muted mb-0">{{ __('tickets.No tickets for this contact') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('tickets.Subject') }}</th>
                            <th>{{ __('tickets.Status') }}</th>
                            <th>{{ __('tickets.Priority') }}</th>
                            <th>{{ __('tickets.Created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contactTickets as $ticket)
                            <tr>
                                <td>
                                    @can('view', $ticket)
                                        <a href="{{ route('ticket.show', $ticket->id) }}" class="text-body fw-medium">{{ $ticket->subject }}</a>
                                    @else
                                        {{ $ticket->subject }}
                                    @endcan
                                </td>
                                <td><span class="badge bg-{{ $ticket->status_color }}">{{ $ticket->status_label }}</span></td>
                                <td>{{ $ticket->priority_label }}</td>
                                <td>{{ $ticket->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
