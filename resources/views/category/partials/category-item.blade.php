<li class="dd-item" data-id="{{ $category->id }}">
    <div class="dd-handle">
        <span class="category-name">{{ $category->name }}</span>
        @if(($showModuleBadge ?? true) && $category->module)
            @php
                $moduleLabel = $category->module->name;
                $sameAsCategoryName = mb_strtolower(trim((string) $category->name)) === mb_strtolower(trim((string) $moduleLabel));
            @endphp
            @if(! $sameAsCategoryName)
                <span class="badge bg-label-info ms-1">{{ $moduleLabel }}</span>
            @endif
        @endif
        @if(! $category->status)
            <span class="badge bg-label-warning ms-1 badge-inactive-status">{{ __('app.Inactive') }}</span>
        @endif
    </div>
    <div class="dd-actions dd-nodrag">
        <div class="btn-group btn-group-sm">
            <a href="{{ route('categories.edit', array_filter(['id' => $category->id, 'module_id' => $indexModuleFilterId ?? null], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-icon btn-outline-primary">
                <i class="ti ti-edit"></i>
            </a>
            <a href="{{ route('categories.create', array_filter(['parent_id' => $category->id, 'module_id' => $indexModuleFilterId ?? $category->module_id])) }}" class="btn btn-icon btn-outline-success">
                <i class="ti ti-plus"></i>
            </a>
            <button type="button"
                class="btn btn-icon toggle-category-status waves-effect {{ $category->status ? 'btn-outline-success' : 'btn-outline-danger' }}"
                data-url="{{ route('categories.toggle-status', $category->id) }}"
                data-active="{{ $category->status ? '1' : '0' }}"
                title="{{ $category->status ? __('app.Deactivate category') : __('app.Activate category') }}">
                <i class="ti {{ $category->status ? 'ti-eye' : 'ti-eye-off' }}"></i>
            </button>
        </div>
    </div>
    
    @if($category->children && $category->children->count() > 0)
        <ol class="dd-list">
            @foreach($category->children as $child)
                @include('category.partials.category-item', [
                    'category' => $child,
                    'showModuleBadge' => $showModuleBadge ?? true,
                    'indexModuleFilterId' => $indexModuleFilterId ?? null,
                ])
            @endforeach
        </ol>
    @endif
</li> 