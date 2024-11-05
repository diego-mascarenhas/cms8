<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('contact.show'))
        <a href="{{ route('contact.show', $responsible_id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
</div>
