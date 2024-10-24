<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('client.edit') && isset($responsible_id))
        <a href="{{ route('contact.edit', $responsible_id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
</div>
