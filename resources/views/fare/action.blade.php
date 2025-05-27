<div class="d-inline-flex">
    <a href="{{ route('fare.show', $fare->id) }}" class="btn btn-icon btn-sm btn-text-secondary rounded-pill">
        <i class="ti ti-eye"></i>
    </a>
    <a href="{{ route('fare.edit', $fare->id) }}" class="btn btn-icon btn-sm btn-text-secondary rounded-pill">
        <i class="ti ti-pencil"></i>
    </a>
</div>