@props([
    'id' => 'categories',
    'label' => 'Categories',
    'selected' => [],
    'moduleKey' => 'contacts'
])

<div class="col-sm-12">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select 
        id="{{ $id }}" 
        name="categories[]" 
        class="form-select categories-select" 
        multiple
    >
        @foreach(\App\Models\Category::getOptions(auth()->user()->currentTeam->id, null, \App\Models\Module::where('key', $moduleKey)->first()->id) as $category)
            <option value="{{ $category['id'] }}" {{ in_array($category['id'], $selected) ? 'selected' : '' }}>
                {{ $category['full_path'] }}
            </option>
        @endforeach
    </select>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#{{ $id }}').select2({
            placeholder: 'Select categories',
            allowClear: true,
            closeOnSelect: false
        });
    });
</script>
@endpush 