<div class="d-flex justify-content-center align-items-center">
    @php
        $ticketRow = isset($id) ? \App\Models\Ticket::find($id) : null;
    @endphp
    @if ($ticketRow)
        @can('view', $ticketRow)
            <a href="{{ route('ticket.show', $ticketRow->id) }}" class="text-body" title="{{ __('tickets.View') }}">
                <i class="ti ti-eye ti-sm me-2"></i>
            </a>
        @endcan
        @can('delete', $ticketRow)
            <a href="#" class="text-danger" title="{{ __('tickets.Delete') }}" onclick="event.preventDefault(); if (confirm('{{ __('tickets.Are you sure you want to delete this ticket?') }}')) { document.getElementById('delete-ticket-{{ $ticketRow->id }}').submit(); }">
                <i class="ti ti-trash ti-sm"></i>
            </a>
            <form id="delete-ticket-{{ $ticketRow->id }}" action="{{ route('ticket.destroy', $ticketRow->id) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endcan
    @endif
</div>
