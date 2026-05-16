@extends('layouts/layoutMaster')

@section('title', 'Team Shortcuts')

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    .shortcut-item {
        cursor: default;
        transition: all 0.3s ease;
    }
    .shortcut-item:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .shortcut-item.sortable-ghost {
        opacity: 0.4;
    }
    .shortcut-item.sortable-chosen {
        transform: scale(1.02);
    }
    .icon-picker {
        position: relative;
    }
    .icon-grid {
        display: none;
        grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
        gap: 8px;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
        background: white;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
    }
    .icon-option {
        padding: 8px;
        text-align: center;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    .icon-option:hover {
        background-color: #f0f0f0;
    }
    .drag-handle {
        cursor: move;
        color: #6c757d;
    }
    .drag-handle:hover {
        color: #495057;
    }
    .shortcut-default-preview {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .shortcut-default-preview .icon-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #f0f2f5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
</style>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Team Settings/</span> Shortcuts</h4>
        <p class="text-muted">Configure the shortcuts that appear in the navbar for quick access</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Settings') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form action="{{ route('team-settings.shortcuts.store', $team) }}" method="POST" id="shortcuts-form">
    @csrf

    {{-- General visibility toggle --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">Show shortcuts icon in navbar</h6>
                    <small class="text-muted">Displays the <i class="ti ti-layout-grid-add"></i> grid icon in the top navigation bar. No icon means shortcuts are hidden for all users in this team.</small>
                </div>
                <div class="form-check form-switch ms-4">
                    <input class="form-check-input" type="checkbox" id="shortcuts_icon_visible"
                           name="shortcuts_icon_visible" value="1" role="switch"
                           {{ $shortcutsIconVisible ? 'checked' : '' }}>
                </div>
            </div>
        </div>
    </div>

    {{-- Unified sortable list --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0">Shortcuts list</h5>
                <small class="text-muted">Drag to reorder. Default shortcuts can be enabled/disabled. Custom shortcuts are fully editable.</small>
            </div>
            <span class="badge bg-label-secondary" id="shortcuts-count-badge">
                {{ count(array_filter($shortcuts, fn($s) => ($s['type'] ?? 'custom') === 'custom')) }} custom
            </span>
        </div>
        <div class="card-body">

            <div id="shortcuts-container">
                @foreach($shortcuts as $index => $shortcut)
                    @if(($shortcut['type'] ?? 'custom') === 'default')
                        {{-- Default shortcut card --}}
                        @php $meta = $availableDefaults[$shortcut['key']] ?? null; @endphp
                        @if($meta)
                            <div class="shortcut-item card mb-3 border {{ ($shortcut['enabled'] ?? false) ? 'border-primary' : '' }}"
                                 data-index="{{ $index }}" data-type="default">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="ti ti-grip-vertical drag-handle fs-5"></i>

                                        <div class="shortcut-default-preview flex-grow-1">
                                            <div class="icon-circle">
                                                <i class="{{ $meta['icon'] }}"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $meta['title'] }}</div>
                                                <small class="text-muted">{{ $meta['subtitle'] }}</small>
                                            </div>
                                        </div>

                                        <span class="badge bg-label-info me-2">Default</span>

                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="shortcuts[{{ $index }}][enabled]" value="0">
                                            <input class="form-check-input shortcut-enabled-toggle" type="checkbox"
                                                   name="shortcuts[{{ $index }}][enabled]" value="1" role="switch"
                                                   {{ ($shortcut['enabled'] ?? false) ? 'checked' : '' }}>
                                        </div>
                                    </div>

                                    <input type="hidden" name="shortcuts[{{ $index }}][type]" value="default">
                                    <input type="hidden" name="shortcuts[{{ $index }}][key]" value="{{ $shortcut['key'] }}">
                                    <input type="hidden" name="shortcuts[{{ $index }}][order]" value="{{ $index }}" class="order-input">
                                </div>
                            </div>
                        @endif
                    @else
                        {{-- Custom shortcut card --}}
                        @php $customEnabled = $shortcut['enabled'] ?? true; @endphp
                        <div class="shortcut-item card mb-3 {{ $customEnabled ? 'border-primary' : '' }}" data-index="{{ $index }}" data-type="custom">
                            <div class="card-body">
                                <div class="row align-items-center mb-3">
                                    <div class="col-auto">
                                        <i class="ti ti-grip-vertical drag-handle fs-5"></i>
                                    </div>
                                    <div class="col">
                                        <h6 class="mb-0 custom-shortcut-label">{{ $shortcut['title'] ?? 'Custom shortcut' }}</h6>
                                        @if (! $customEnabled)
                                            <small class="text-muted">Hidden from navbar</small>
                                        @endif
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check form-switch mb-0" title="Show in navbar">
                                            <input type="hidden" name="shortcuts[{{ $index }}][enabled]" value="0">
                                            <input class="form-check-input shortcut-enabled-toggle" type="checkbox"
                                                   name="shortcuts[{{ $index }}][enabled]" value="1" role="switch"
                                                   {{ $customEnabled ? 'checked' : '' }}>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-shortcut">
                                            <i class="ti ti-trash me-1"></i>Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Icon <span class="text-danger">*</span></label>
                                        <div class="icon-picker">
                                            <input type="text"
                                                   name="shortcuts[{{ $index }}][icon]"
                                                   class="form-control icon-input"
                                                   placeholder="ti ti-calendar"
                                                   value="{{ old('shortcuts.'.$index.'.icon', $shortcut['icon'] ?? '') }}"
                                                   required>
                                            <div class="icon-grid" id="icon-grid-{{ $index }}"></div>
                                        </div>
                                        <small class="text-muted">Click to pick an icon</small>
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
                                               placeholder="Quick note"
                                               value="{{ old('shortcuts.'.$index.'.subtitle', $shortcut['subtitle'] ?? '') }}">
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="shortcuts[{{ $index }}][open_in_new_tab]" value="1"
                                                   id="new_tab_{{ $index }}"
                                                   {{ old('shortcuts.'.$index.'.open_in_new_tab', $shortcut['open_in_new_tab'] ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="new_tab_{{ $index }}">
                                                Open in new tab/window
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="shortcuts[{{ $index }}][type]" value="custom">
                                <input type="hidden" name="shortcuts[{{ $index }}][order]" value="{{ $index }}" class="order-input">
                            </div>
                        </div>
                    @endif
                @endforeach

                @if(count(array_filter($shortcuts, fn($s) => ($s['type'] ?? 'custom') === 'custom')) === 0)
                    <div class="text-center py-3 text-muted" id="no-custom-notice">
                        <small>No custom shortcuts yet — click <strong>Add Custom Shortcut</strong> below to create one.</small>
                    </div>
                @endif
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <button type="button" class="btn btn-outline-primary" id="add-shortcut">
                    <i class="ti ti-plus me-1"></i>Add Custom Shortcut
                </button>
                <div>
                    <button type="button" class="btn btn-label-secondary me-2"
                            onclick="location.href='{{ route('team-settings.index', $team) }}'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Shortcuts</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container    = document.getElementById('shortcuts-container');
    const addButton    = document.getElementById('add-shortcut');
    const countBadge   = document.getElementById('shortcuts-count-badge');
    const noCustomNote = document.getElementById('no-custom-notice');

    let totalIndex = {{ count($shortcuts) }};

    const popularIcons = [
        'ti ti-calendar', 'ti ti-users', 'ti ti-file-invoice', 'ti ti-settings',
        'ti ti-dashboard', 'ti ti-chart-bar', 'ti ti-mail', 'ti ti-phone',
        'ti ti-bell', 'ti ti-star', 'ti ti-heart', 'ti ti-bookmark',
        'ti ti-folder', 'ti ti-file', 'ti ti-download', 'ti ti-upload',
        'ti ti-edit', 'ti ti-trash', 'ti ti-plus', 'ti ti-minus',
        'ti ti-check', 'ti ti-x', 'ti ti-arrow-right', 'ti ti-arrow-left',
        'ti ti-search', 'ti ti-filter', 'ti ti-refresh', 'ti ti-save',
        'ti ti-share', 'ti ti-link', 'ti ti-external-link', 'ti ti-copy',
        'ti ti-printer', 'ti ti-camera', 'ti ti-video', 'ti ti-music',
        'ti ti-image', 'ti ti-palette', 'ti ti-brush', 'ti ti-paint',
        'ti ti-code', 'ti ti-terminal', 'ti ti-database', 'ti ti-server',
        'ti ti-cloud', 'ti ti-wifi', 'ti ti-bluetooth', 'ti ti-battery',
        'ti ti-lock', 'ti ti-key', 'ti ti-shield', 'ti ti-eye',
        'ti ti-eye-off', 'ti ti-user', 'ti ti-user-plus', 'ti ti-user-minus',
        'ti ti-home', 'ti ti-building', 'ti ti-map-pin', 'ti ti-navigation',
        'ti ti-car', 'ti ti-plane', 'ti ti-train', 'ti ti-bike',
        'ti ti-coffee', 'ti ti-utensils', 'ti ti-shopping-cart', 'ti ti-credit-card',
        'ti ti-wallet', 'ti ti-piggy-bank', 'ti ti-coins', 'ti ti-receipt',
        'ti ti-gift', 'ti ti-party-horn', 'ti ti-cake', 'ti ti-balloon',
        'ti ti-sun', 'ti ti-moon', 'ti ti-weather-sunny', 'ti ti-weather-rainy',
        'ti ti-weather-snow', 'ti ti-thermometer', 'ti ti-droplet', 'ti ti-flame'
    ];

    // Sortable (unified list)
    Sortable.create(container, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        filter: '.no-drag',
        onEnd: updateOrder,
    });

    // Add custom shortcut
    addButton.addEventListener('click', function () {
        const customCount = container.querySelectorAll('[data-type="custom"]').length;
        if (customCount >= 6) {
            alert('Maximum 6 custom shortcuts allowed');
            return;
        }

        if (noCustomNote) { noCustomNote.remove(); }

        container.insertAdjacentHTML('beforeend', buildCustomCard(totalIndex));
        totalIndex++;
        updateOrder();
        initIconPickers();
        updateCountBadge();
    });

    // Remove custom shortcut
    container.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-shortcut') || e.target.closest('.remove-shortcut')) {
            e.target.closest('.shortcut-item').remove();
            updateOrder();
            updateCountBadge();
        }
    });

    // Shortcut enable toggle: highlight card border
    container.addEventListener('change', function (e) {
        if (e.target.classList.contains('shortcut-enabled-toggle')) {
            const card = e.target.closest('.shortcut-item');
            card.classList.toggle('border-primary', e.target.checked);
            const label = card.querySelector('.custom-shortcut-label');
            if (label) {
                let hint = card.querySelector('.shortcut-hidden-hint');
                if (e.target.checked) {
                    hint?.remove();
                } else if (!hint) {
                    hint = document.createElement('small');
                    hint.className = 'text-muted shortcut-hidden-hint';
                    hint.textContent = 'Hidden from navbar';
                    label.after(hint);
                }
            }
        }
    });

    function updateOrder() {
        container.querySelectorAll('.shortcut-item').forEach((item, idx) => {
            item.setAttribute('data-index', idx);
            const orderInput = item.querySelector('.order-input');
            if (orderInput) { orderInput.value = idx; }
        });
    }

    function updateCountBadge() {
        const count = container.querySelectorAll('[data-type="custom"]').length;
        countBadge.textContent = count + ' custom';
    }

    function buildCustomCard(index) {
        return `
        <div class="shortcut-item card mb-3 border-primary" data-index="${index}" data-type="custom">
            <div class="card-body">
                <div class="row align-items-center mb-3">
                    <div class="col-auto">
                        <i class="ti ti-grip-vertical drag-handle fs-5"></i>
                    </div>
                    <div class="col">
                        <h6 class="mb-0 custom-shortcut-label">Custom shortcut</h6>
                    </div>
                    <div class="col-auto d-flex align-items-center gap-2">
                        <div class="form-check form-switch mb-0" title="Show in navbar">
                            <input type="hidden" name="shortcuts[${index}][enabled]" value="0">
                            <input class="form-check-input shortcut-enabled-toggle" type="checkbox"
                                   name="shortcuts[${index}][enabled]" value="1" role="switch" checked>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-shortcut">
                            <i class="ti ti-trash me-1"></i>Remove
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Icon <span class="text-danger">*</span></label>
                        <div class="icon-picker">
                            <input type="text" name="shortcuts[${index}][icon]"
                                   class="form-control icon-input" placeholder="ti ti-calendar" required>
                            <div class="icon-grid" id="icon-grid-${index}"></div>
                        </div>
                        <small class="text-muted">Click to pick an icon</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="shortcuts[${index}][title]"
                               class="form-control" placeholder="Calendar" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">URL <span class="text-danger">*</span></label>
                        <input type="text" name="shortcuts[${index}][url]"
                               class="form-control" placeholder="/app/calendar" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Subtitle</label>
                        <input type="text" name="shortcuts[${index}][subtitle]"
                               class="form-control" placeholder="Quick note">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="shortcuts[${index}][open_in_new_tab]" value="1"
                                   id="new_tab_${index}">
                            <label class="form-check-label" for="new_tab_${index}">
                                Open in new tab/window
                            </label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="shortcuts[${index}][type]" value="custom">
                <input type="hidden" name="shortcuts[${index}][order]" value="${index}" class="order-input">
            </div>
        </div>`;
    }

    function initIconPickers() {
        document.querySelectorAll('.icon-input').forEach(input => {
            if (input.dataset.initialized) { return; }
            input.dataset.initialized = 'true';

            input.addEventListener('click', function () {
                const grid = this.parentNode.querySelector('.icon-grid');
                const isOpen = grid.style.display === 'grid';
                document.querySelectorAll('.icon-grid').forEach(g => { g.style.display = 'none'; });
                if (!isOpen) {
                    grid.style.display = 'grid';
                    populateIconGrid(grid, input);
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.icon-picker')) {
                document.querySelectorAll('.icon-grid').forEach(g => { g.style.display = 'none'; });
            }
        });
    }

    function populateIconGrid(grid, input) {
        if (grid.dataset.populated) { return; }
        grid.innerHTML = '';
        popularIcons.forEach(iconClass => {
            const div = document.createElement('div');
            div.className = 'icon-option';
            div.innerHTML = `<i class="${iconClass}"></i>`;
            div.title = iconClass;
            div.addEventListener('click', function () {
                input.value = iconClass;
                grid.style.display = 'none';
            });
            grid.appendChild(div);
        });
        grid.dataset.populated = 'true';
    }

    // Re-index names before submit so PHP receives sequential keys
    document.getElementById('shortcuts-form').addEventListener('submit', function () {
        container.querySelectorAll('.shortcut-item').forEach((item, idx) => {
            item.querySelectorAll('[name]').forEach(field => {
                field.name = field.name.replace(/shortcuts\[\d+\]/, `shortcuts[${idx}]`);
            });
        });
    });

    initIconPickers();
});
</script>
@endsection
