<li class="dd-item" data-id="{{ $category->id }}">
    <div class="dd-handle d-flex align-items-center flex-wrap gap-2">
        <i class="ti ti-grip-vertical text-muted flex-shrink-0" title="{{ __('app.Drag to reorder categories') }}" aria-hidden="true"></i>
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
        <div class="d-flex align-items-center justify-content-end gap-1">
            <a href="{{ route('categories.edit', array_filter(['id' => $category->id, 'module_id' => $indexModuleFilterId ?? null], fn ($v) => $v !== null && $v !== '')) }}"
                class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none"
                title="{{ __('app.Edit Category') }}">
                <i class="ti ti-edit ti-sm"></i>
            </a>
            <a href="{{ route('categories.create', array_filter(['parent_id' => $category->id, 'module_id' => $indexModuleFilterId ?? $category->module_id])) }}"
                class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none"
                title="{{ __('app.New Category') }}">
                <i class="ti ti-plus ti-sm text-success"></i>
            </a>
            <a href="{{ route('categories.duplicate', $category->id) }}"
                class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none"
                title="Duplicate category">
                <i class="ti ti-copy ti-sm text-primary"></i>
            </a>
            <button type="button"
                class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none toggle-category-status"
                data-url="{{ route('categories.toggle-status', $category->id) }}"
                data-active="{{ $category->status ? '1' : '0' }}"
                title="{{ $category->status ? __('app.Deactivate category') : __('app.Activate category') }}">
                <i class="ti {{ $category->status ? 'ti-eye ti-sm text-success' : 'ti-eye-off ti-sm text-danger' }}"></i>
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