@props(['selectedMultimedia' => []])

<div class="mb-3">
    <label class="form-label">{{ __('app.Associated Multimedia') }}</label>
    <select id="multimedia_selector" name="multimedia[]" class="form-select select2" multiple>
        @foreach($selectedMultimedia as $multimediaId)
            @php
                $multimedia = \App\Models\Multimedia::find($multimediaId);
            @endphp
            @if($multimedia)
                <option value="{{ $multimedia->id }}" selected>
                    {{ $multimedia->title }}
                </option>
            @endif
        @endforeach
    </select>
    <div class="form-text">{{ __('app.Select multimedia items to associate with this content') }}</div>
</div>

<div id="multimedia_preview" class="row g-2 mt-2">
    @foreach($selectedMultimedia as $multimediaId)
        @php
            $multimedia = \App\Models\Multimedia::find($multimediaId);
        @endphp
        @if($multimedia)
            <div class="col-md-2 multimedia-item" data-id="{{ $multimedia->id }}">
                @php
                    $previewUrl = $multimedia->getFirstMediaUrl('poster')
                        ?: $multimedia->getFirstMediaUrl('media', 'poster')
                        ?: $multimedia->getFirstMediaUrl('media', 'thumb')
                        ?: $multimedia->getFirstMediaUrl('media');
                @endphp
                @if($previewUrl)
                    <img src="{{ $previewUrl }}" alt="{{ $multimedia->title }}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                @else
                    <div class="img-thumbnail d-flex align-items-center justify-content-center" style="width: 100%; height: 100px; background: #f0f0f0;">
                        <i class="ti ti-file"></i>
                    </div>
                @endif
                <small class="d-block text-center mt-1">{{ Str::limit($multimedia->title, 15) }}</small>
            </div>
        @endif
    @endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#multimedia_selector').select2({
        ajax: {
            url: '{{ route("multimedia.index") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                    view: 'cards',
                    per_page: 20
                };
            },
            processResults: function (data) {
                if (data.cards && Array.isArray(data.cards)) {
                    return {
                        results: data.cards.map(function(item) {
                            return {
                                id: item.id,
                                text: item.title
                            };
                        })
                    };
                }
                return { results: [] };
            },
            cache: true
        },
        placeholder: '{{ __("app.Search multimedia...") }}',
        minimumInputLength: 2,
        allowClear: true
    });

    $('#multimedia_selector').on('select2:select select2:unselect', function() {
        updateMultimediaPreview();
    });

    function updateMultimediaPreview() {
        const selected = $('#multimedia_selector').val() || [];
        // This would need to be implemented with AJAX to fetch previews
        // For now, we'll just show the selected count
        console.log('Selected multimedia:', selected);
    }
});
</script>
