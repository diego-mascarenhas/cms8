<div class="d-inline-block">
    <a href="javascript:;" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
        <i class="bx bx-dots-vertical-rounded"></i>
    </a>
    <ul class="dropdown-menu dropdown-menu-end m-0">
        <li>
            <a href="{{ route('domain.show', $id) }}" class="dropdown-item">View</a>
        </li>
        <li>
            <a href="{{ route('domain.edit', $id) }}" class="dropdown-item">Edit</a>
        </li>
        <li>
            <form action="{{ route('domain.destroy', $id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
        </li>
    </ul>
</div> 