@php
    $categoryOptions = $categoryOptions ?? [];
    $showSuggestion = $showSuggestion ?? false;
    $modalId = $modalId ?? 'lineCategoryModal';
    $selectId = $selectId ?? 'line_category_modal_select';
    $livewireKey = $livewireKey ?? 'line-cat-mgr-services';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}Label">{{ __('Item category') }}</h5>
                <div class="d-flex align-items-center gap-1">
                    @can('viewAny', \App\Models\Category::class)
                        @livewire(\App\Livewire\ModuleCategoriesManagerModal::class, ['moduleKey' => 'services', 'linkedSelectId' => $selectId], key($livewireKey))
                    @endcan
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
            <div class="modal-body">
                @if ($showSuggestion)
                    <div id="line-category-suggestion" class="alert alert-primary d-none mb-3" role="status">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <div>
                                <div class="fw-medium mb-1">{{ __('Suggested from previous invoice') }}</div>
                                <div class="small" id="line-category-suggestion-text"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" id="apply-line-category-suggestion">{{ __('Use suggestion') }}</button>
                        </div>
                    </div>
                @endif
                <div id="line-category-empty" class="alert alert-warning {{ count($categoryOptions) ? 'd-none' : '' }} mb-3" role="status">
                    {{ __('No service categories yet. Use the gear to create them, or type a name in the search and press Add.') }}
                </div>
                <label for="{{ $selectId }}" class="form-label">{{ __('Select a category') }}</label>
                <select
                    id="{{ $selectId }}"
                    class="form-select"
                    data-placeholder="{{ __('Uncategorized') }}"
                    data-allow-clear="true"
                    data-module-key="services"
                    data-empty-text="{{ __('Uncategorized') }}"
                    data-show-empty-option="1"
                    data-allow-empty-select="1"
                >
                    <option value="">{{ __('Uncategorized') }}</option>
                    @foreach (collect($categoryOptions)->groupBy(fn ($option) => $option['group'] ?? '') as $groupLabel => $groupOptions)
                        @if ($groupLabel !== '')
                            <optgroup label="{{ $groupLabel }}">
                        @endif
                        @foreach ($groupOptions as $categoryOption)
                            <option value="{{ $categoryOption['id'] }}">{{ $categoryOption['name'] }}</option>
                        @endforeach
                        @if ($groupLabel !== '')
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-label-secondary" id="clear-line-category">{{ __('Clear') }}</button>
                <button type="button" class="btn btn-primary" id="save-line-category">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
