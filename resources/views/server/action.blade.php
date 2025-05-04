<div class="dropdown">
    <button type="button" class="btn btn-sm dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
        <i class="fas fa-ellipsis-vertical"></i>
    </button>
    <div class="dropdown-menu">
        <a class="dropdown-item" href="{{ route('server.show', $id) }}">
            <i class="fas fa-eye me-1"></i> View
        </a>
        <a class="dropdown-item" href="{{ route('server.edit', $id) }}">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <form action="{{ route('server.destroy', $id) }}" method="POST" class="d-inline" 
              onsubmit="return confirm('Are you sure you want to delete this server?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="dropdown-item">
                <i class="fas fa-trash me-1"></i> Delete
            </button>
        </form>
    </div>
</div> 