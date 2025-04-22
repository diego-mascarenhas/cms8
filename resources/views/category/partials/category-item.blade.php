<li class="dd-item" data-id="{{ $category->id }}">
    <div class="dd-handle">
        <span class="category-name">{{ $category->name }}</span>
        @if($category->module)
            <span class="badge bg-label-info ms-1">{{ $category->module->name }}</span>
        @endif
        @if(!$category->status)
            <span class="badge bg-label-warning ms-1">Inactive</span>
        @endif
    </div>
    <div class="dd-actions">
        <div class="btn-group btn-group-sm">
            <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-icon btn-outline-primary">
                <i class="ti ti-edit"></i>
            </a>
            <a href="{{ route('categories.create', ['parent_id' => $category->id]) }}" class="btn btn-icon btn-outline-success">
                <i class="ti ti-plus"></i>
            </a>
            <a href="#" class="btn btn-icon btn-outline-danger delete-category" 
               data-url="{{ route('categories.destroy', $category->id) }}"
               data-name="{{ $category->name }}">
                <i class="ti ti-trash"></i>
            </a>
        </div>
    </div>
    
    @if($category->children && $category->children->count() > 0)
        <ol class="dd-list">
            @foreach($category->children as $child)
                @include('category.partials.category-item', ['category' => $child])
            @endforeach
        </ol>
    @endif
</li> 