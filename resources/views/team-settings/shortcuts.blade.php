@extends('layouts/layoutMaster')

@section('title', 'Team Shortcuts')

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Team Settings/</span> Shortcuts</h4>
        <p class="text-muted">Configure custom shortcuts that appear in the navbar for quick access</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>Back to Settings
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Team Shortcuts Configuration</h5>
        <p class="text-muted mb-0">Add up to 6 shortcuts that will appear in the navbar dropdown</p>
    </div>
    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('team-settings.shortcuts.store', $team) }}" method="POST" id="shortcuts-form">
            @csrf

            <div id="shortcuts-container">
                @if(count($shortcuts) > 0)
                    @foreach($shortcuts as $index => $shortcut)
                        <div class="shortcut-item card mb-3" data-index="{{ $index }}">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Icon <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="shortcuts[{{ $index }}][icon]"
                                               class="form-control"
                                               placeholder="ti ti-calendar"
                                               value="{{ old('shortcuts.'.$index.'.icon', $shortcut['icon'] ?? '') }}"
                                               required>
                                        <small class="text-muted">Use Tabler Icons format: ti ti-icon-name</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="shortcuts[{{ $index }}][title]"
                                               class="form-control"
                                               placeholder="Calendar"
                                               value="{{ old('shortcuts.'.$index.'.title', $shortcut['title'] ?? '') }}"
                                               required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">URL <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="shortcuts[{{ $index }}][url]"
                                               class="form-control"
                                               placeholder="/app/calendar"
                                               value="{{ old('shortcuts.'.$index.'.url', $shortcut['url'] ?? '') }}"
                                               required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Subtitle</label>
                                        <input type="text"
                                               name="shortcuts[{{ $index }}][subtitle]"
                                               class="form-control"
                                               placeholder="Appointments"
                                               value="{{ old('shortcuts.'.$index.'.subtitle', $shortcut['subtitle'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-shortcut">
                                            <i class="ti ti-trash me-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-4">
                        <i class="ti ti-layout-grid-add mb-3" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted">No shortcuts configured yet. Click "Add Shortcut" to get started.</p>
                    </div>
                @endif
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <button type="button" class="btn btn-outline-primary" id="add-shortcut">
                    <i class="ti ti-plus me-1"></i>Add Shortcut
                </button>

                <div>
                    <button type="button" class="btn btn-label-secondary me-2" onclick="location.href='{{ route('team-settings.index', $team) }}'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Shortcuts</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Preview Card -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Preview</h5>
        <p class="text-muted mb-0">This is how your shortcuts will appear in the navbar</p>
    </div>
    <div class="card-body">
        <div class="dropdown-shortcuts-list">
            <div class="row row-bordered overflow-visible g-0" id="preview-container">
                <!-- Preview will be generated here -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let shortcutIndex = {{ count($shortcuts) }};
    const container = document.getElementById('shortcuts-container');
    const addButton = document.getElementById('add-shortcut');
    const previewContainer = document.getElementById('preview-container');

    // Add shortcut functionality
    addButton.addEventListener('click', function() {
        if (shortcutIndex >= 6) {
            alert('Maximum 6 shortcuts allowed');
            return;
        }

        const shortcutHtml = createShortcutHtml(shortcutIndex);
        container.insertAdjacentHTML('beforeend', shortcutHtml);
        shortcutIndex++;
        updatePreview();
    });

    // Remove shortcut functionality
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-shortcut') || e.target.closest('.remove-shortcut')) {
            const shortcutItem = e.target.closest('.shortcut-item');
            shortcutItem.remove();
            updatePreview();
        }
    });

    // Update preview on input change
    container.addEventListener('input', function(e) {
        if (e.target.matches('input[name*="[title]"], input[name*="[subtitle]"], input[name*="[url]"], input[name*="[icon]"]')) {
            updatePreview();
        }
    });

    function createShortcutHtml(index) {
        return `
            <div class="shortcut-item card mb-3" data-index="${index}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Icon <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="shortcuts[${index}][icon]"
                                   class="form-control"
                                   placeholder="ti ti-calendar"
                                   required>
                            <small class="text-muted">Use Tabler Icons format: ti ti-icon-name</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="shortcuts[${index}][title]"
                                   class="form-control"
                                   placeholder="Calendar"
                                   required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">URL <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="shortcuts[${index}][url]"
                                   class="form-control"
                                   placeholder="/app/calendar"
                                   required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Subtitle</label>
                            <input type="text"
                                   name="shortcuts[${index}][subtitle]"
                                   class="form-control"
                                   placeholder="Appointments">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-shortcut">
                                <i class="ti ti-trash me-1"></i>Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function updatePreview() {
        const shortcuts = [];
        const shortcutItems = container.querySelectorAll('.shortcut-item');

        shortcutItems.forEach(item => {
            const title = item.querySelector('input[name*="[title]"]')?.value || '';
            const subtitle = item.querySelector('input[name*="[subtitle]"]')?.value || '';
            const url = item.querySelector('input[name*="[url]"]')?.value || '';
            const icon = item.querySelector('input[name*="[icon]"]')?.value || '';

            if (title && url && icon) {
                shortcuts.push({ title, subtitle, url, icon });
            }
        });

        let previewHtml = '';
        shortcuts.forEach((shortcut, index) => {
            const isEven = index % 2 === 0;
            const isLast = index === shortcuts.length - 1;

            if (isEven) {
                previewHtml += '<div class="row row-bordered overflow-visible g-0">';
            }

            previewHtml += `
                <div class="dropdown-shortcuts-item col">
                    <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="${shortcut.icon} fs-4"></i>
                    </span>
                    <a href="${shortcut.url}" class="stretched-link">${shortcut.title}</a>
                    <small class="text-muted mb-0">${shortcut.subtitle || ''}</small>
                </div>
            `;

            if (!isEven || isLast) {
                previewHtml += '</div>';
            }
        });

        previewContainer.innerHTML = previewHtml || '<div class="text-center text-muted py-4">No shortcuts to preview</div>';
    }

    // Initial preview update
    updatePreview();
});
</script>
@endsection
