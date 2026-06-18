@extends('layouts/layoutMaster')

@section('title', __('app.Media'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('app.Media') }}</h4>
        <p class="text-muted">{{ __('app.Manage your media library') }}</p>
    </div>
    @can('create', \App\Models\Post::class)
    <div class="mt-3 mt-md-0">
        <label class="btn btn-primary mb-0">
            <i class="ti ti-upload me-1"></i> {{ __('app.Upload') }}
            <input type="file" id="media-upload-input" class="d-none" multiple>
        </label>
    </div>
    @endcan
</div>

@if(!empty($wordpressSyncEnabled))
<div class="alert alert-info d-flex align-items-center">
    <i class="ti ti-info-circle me-2"></i>
    <span>{{ __('app.Uploaded files are synced to the WordPress media library.') }}</span>
</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3" id="media-grid">
            @forelse($media as $item)
                @php $thumb = $item->getMeta('_humano_thumb_url') ?: $item->guid; @endphp
                <div class="col-6 col-md-3 col-lg-2" id="media-card-{{ $item->id }}">
                    <div class="card h-100 border">
                        <div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center overflow-hidden">
                            @if(str_starts_with((string) $item->post_mime_type, 'image/'))
                                <img src="{{ $thumb }}" alt="{{ $item->post_title }}" style="object-fit: cover; width:100%; height:100%;">
                            @else
                                <i class="ti ti-file ti-lg text-muted"></i>
                            @endif
                        </div>
                        <div class="card-body p-2">
                            <p class="small text-truncate mb-1" title="{{ $item->post_title }}">{{ $item->post_title }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                @if($item->wp_id)
                                    <span class="badge bg-label-success" title="WordPress #{{ $item->wp_id }}"><i class="ti ti-brand-wordpress"></i></span>
                                @else
                                    <span class="badge bg-label-secondary" title="{{ __('app.Local only') }}"><i class="ti ti-device-laptop"></i></span>
                                @endif
                                @can('delete', $item)
                                <a href="#" class="text-danger" onclick="deleteMedia({{ $item->id }}); return false;"><i class="ti ti-trash"></i></a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5" id="media-empty">{{ __('app.No media yet. Upload your first file.') }}</div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $media->links() }}
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
const uploadUrl = '{{ route('cms.media.store') }}';
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

document.getElementById('media-upload-input')?.addEventListener('change', async function(e) {
    const files = Array.from(e.target.files || []);
    for (const file of files) {
        const form = new FormData();
        form.append('file', file);
        try {
            const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: form });
            const data = await res.json();
            if (data.success) { prependCard(data.media); }
        } catch (err) { console.error(err); }
    }
    e.target.value = '';
});

function prependCard(m) {
    document.getElementById('media-empty')?.remove();
    const grid = document.getElementById('media-grid');
    const inner = m.is_image
        ? `<img src="${m.thumb}" alt="" style="object-fit:cover;width:100%;height:100%;">`
        : `<i class="ti ti-file ti-lg text-muted"></i>`;
    const col = document.createElement('div');
    col.className = 'col-6 col-md-3 col-lg-2';
    col.id = 'media-card-' + m.id;
    col.innerHTML = `<div class="card h-100 border"><div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center overflow-hidden">${inner}</div>`
        + `<div class="card-body p-2"><p class="small text-truncate mb-1" title="${m.title}">${m.title}</p>`
        + `<div class="d-flex justify-content-between align-items-center"><span class="badge bg-label-secondary"><i class="ti ti-device-laptop"></i></span>`
        + `<a href="#" class="text-danger" onclick="deleteMedia(${m.id}); return false;"><i class="ti ti-trash"></i></a></div></div></div>`;
    grid.prepend(col);
}

function deleteMedia(id) {
    Swal.fire({
        title: '{{ __("app.Are you sure?") }}',
        text: '{{ __("app.This action cannot be undone.") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("app.Yes, delete it") }}',
        cancelButtonText: '{{ __("app.Cancel") }}',
        customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-secondary' },
        buttonsStyling: false
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('{{ url('cms/media') }}/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { if (d.success) document.getElementById('media-card-' + id)?.remove(); });
    });
}
</script>
@endsection
