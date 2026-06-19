{{-- Reusable media picker modal. Opens with openMediaPicker(callback); callback receives {id,url,thumb,title,is_image}. --}}
<div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('app.Media library') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <input type="text" id="media-picker-search" class="form-control me-2" placeholder="{{ __('app.Search') }}" style="max-width: 280px;">
                    <label class="btn btn-label-primary mb-0">
                        <i class="ti ti-upload me-1"></i>{{ __('app.Upload') }}
                        <input type="file" id="media-picker-upload" class="d-none">
                    </label>
                </div>
                <div class="row g-2" id="media-picker-grid"></div>
                <div class="text-center text-muted py-4 d-none" id="media-picker-empty">{{ __('app.No media yet. Upload your first file.') }}</div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const listUrl = '{{ route('cms.media.list') }}';
    const uploadUrl = '{{ route('cms.media.store') }}';
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let callback = null;
    let modal = null;

    function getModal() {
        if (!modal) { modal = new bootstrap.Modal(document.getElementById('mediaPickerModal')); }
        return modal;
    }

    window.openMediaPicker = function(cb) {
        callback = cb;
        loadMedia('');
        getModal().show();
    };

    function loadMedia(search) {
        fetch(listUrl + '?search=' + encodeURIComponent(search), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => renderGrid(data.data || []));
    }

    function renderGrid(items) {
        const grid = document.getElementById('media-picker-grid');
        const empty = document.getElementById('media-picker-empty');
        grid.innerHTML = '';
        if (!items.length) { empty.classList.remove('d-none'); return; }
        empty.classList.add('d-none');
        items.forEach(m => {
            const col = document.createElement('div');
            col.className = 'col-4 col-md-3';
            const inner = m.is_image
                ? `<img src="${m.thumb}" alt="" style="object-fit:cover;width:100%;height:100%;">`
                : `<i class="ti ti-file ti-lg text-muted"></i>`;
            col.innerHTML = `<div class="card border media-pick-item" style="cursor:pointer;"><div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center overflow-hidden">${inner}</div>`
                + `<div class="card-body p-1"><p class="small text-truncate mb-0" title="${m.title}">${m.title}</p></div></div>`;
            col.querySelector('.media-pick-item').addEventListener('click', () => {
                if (callback) callback(m);
                getModal().hide();
            });
            grid.appendChild(col);
        });
    }

    let searchTimer = null;
    document.getElementById('media-picker-search')?.addEventListener('keyup', function() {
        clearTimeout(searchTimer);
        const v = this.value;
        searchTimer = setTimeout(() => loadMedia(v), 300);
    });

    document.getElementById('media-picker-upload')?.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: form });
        const data = await res.json();
        e.target.value = '';
        if (data.success) { loadMedia(''); }
    });
})();
</script>
