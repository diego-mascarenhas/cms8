@extends('layouts/layoutMaster')

@section('title', __('Gallery Order'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/nestable/nestable.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/nestable/jquery.nestable.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Multimedia') }}/</span>
            {{ $galleryTag->name }}
        </h4>
        <p class="text-muted">{{ __('Reorder items in this gallery') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('multimedia.index', ['gallery_tag_id' => $galleryTag->id]) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($items->isEmpty())
            <div class="text-center text-muted py-5">{{ __('No items found in this gallery.') }}</div>
        @else
            <div class="dd" id="gallery-nestable">
                <ol class="dd-list">
                    @foreach($items as $item)
                        <li class="dd-item" data-id="{{ $item->id }}">
                            <div class="dd-handle d-flex align-items-center gap-3">
                                @php
                                    $previewUrl = $item->getFirstMediaUrl('poster')
                                        ?: $item->getFirstMediaUrl('media', 'poster')
                                        ?: $item->getFirstMediaUrl('media', 'thumb');
                                    $previewUrl = $previewUrl ?: $item->getFirstMediaUrl('media');
                                @endphp
                                @if($previewUrl)
                                    <img src="{{ $previewUrl }}" alt="{{ $item->title }}" class="rounded" width="60" height="60">
                                @else
                                    <span class="avatar avatar-sm">
                                        <span class="avatar-initial rounded bg-label-secondary">
                                            <i class="ti ti-file ti-sm"></i>
                                        </span>
                                    </span>
                                @endif
                                <div>
                                    <div class="fw-medium">{{ $item->title }}</div>
                                    <small class="text-muted">{{ $item->type }}</small>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
            <div class="mt-3">
                <button type="button" id="saveGalleryOrder" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>{{ __('Save Order') }}
                </button>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const galleryNestable = $('#gallery-nestable');
        if (!galleryNestable.length) {
            return;
        }

        galleryNestable.nestable({ maxDepth: 1 });

        $('#saveGalleryOrder').on('click', function () {
            const data = galleryNestable.nestable('serialize');
            const items = data.map(function (item, index) {
                return { id: item.id, order: index };
            });

            fetch('{{ route("multimedia.gallery.order") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    gallery_tag_id: {{ $galleryTag->id }},
                    items: items
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.fire({
                    icon: 'success',
                    title: '{{ __("Saved") }}',
                    text: data.success
                });
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("Error") }}',
                    text: '{{ __("Failed to update order.") }}'
                });
            });
        });
    });
</script>
@endpush
