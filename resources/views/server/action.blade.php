<div class="dropdown">
    <button type="button" class="btn dropdown-toggle hide-arrow p-0" data-bs-toggle="dropdown">
        <i class="ti ti-dots-vertical"></i>
    </button>
    <div class="dropdown-menu">
        <a class="dropdown-item" href="{{ route('server.show', $id) }}">
            <i class="ti ti-eye me-1"></i> View
        </a>
        <a class="dropdown-item" href="{{ route('server.edit', $id) }}">
            <i class="ti ti-edit me-1"></i> Edit
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this server?')) document.getElementById('delete-form-{{ $id }}').submit();">
            <i class="ti ti-trash me-1"></i> Delete
        </a>
        <form id="delete-form-{{ $id }}" action="{{ route('server.destroy', $id) }}" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div> 