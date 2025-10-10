@extends('layouts/layoutMaster')

@section('title', 'Team Shortcuts')

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
<style>
    .shortcut-item {
        cursor: move;
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
        display: grid;
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
        display: none;
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
    .icon-option.selected {
        background-color: #007bff;
        color: white;
    }
    .drag-handle {
        cursor: move;
        color: #6c757d;
    }
    .drag-handle:hover {
        color: #495057;
    }
</style>
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
                                <div class="row align-items-center mb-3">
                                    <div class="col-auto">
                                        <i class="ti ti-grip-vertical drag-handle" style="font-size: 1.2rem;"></i>
                                    </div>
                                    <div class="col">
                                        <h6 class="mb-0">Shortcut #{{ $index + 1 }}</h6>
                                    </div>
                                    <div class="col-auto">
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
                                            <div class="icon-grid" id="icon-grid-{{ $index }}">
                                                <!-- Icons will be populated by JavaScript -->
                                            </div>
                                        </div>
                                        <small class="text-muted">Click to select from popular icons</small>
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

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="shortcuts[{{ $index }}][open_in_new_tab]"
                                                   value="1"
                                                   id="new_tab_{{ $index }}"
                                                   {{ old('shortcuts.'.$index.'.open_in_new_tab', $shortcut['open_in_new_tab'] ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="new_tab_{{ $index }}">
                                                Open in new tab/window
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="hidden" name="shortcuts[{{ $index }}][order]" value="{{ $index }}">
                                        <small class="text-muted">Order: {{ $index + 1 }}</small>
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


@endsection

@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let shortcutIndex = {{ count($shortcuts) }};
    const container = document.getElementById('shortcuts-container');
    const addButton = document.getElementById('add-shortcut');

    // Popular Tabler Icons for the picker
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

    // Initialize Sortable for drag and drop
    let sortable = Sortable.create(container, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function(evt) {
            updateOrder();
        }
    });

    // Add shortcut functionality
    addButton.addEventListener('click', function() {
        if (shortcutIndex >= 6) {
            alert('Maximum 6 shortcuts allowed');
            return;
        }

        const shortcutHtml = createShortcutHtml(shortcutIndex);
        container.insertAdjacentHTML('beforeend', shortcutHtml);
        shortcutIndex++;
        updateOrder();
        initializeIconPickers();
    });

    // Remove shortcut functionality
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-shortcut') || e.target.closest('.remove-shortcut')) {
            const shortcutItem = e.target.closest('.shortcut-item');
            shortcutItem.remove();
            updateOrder();
        }
    });


    function createShortcutHtml(index) {
        return `
            <div class="shortcut-item card mb-3" data-index="${index}">
                <div class="card-body">
                    <div class="row align-items-center mb-3">
                        <div class="col-auto">
                            <i class="ti ti-grip-vertical drag-handle" style="font-size: 1.2rem;"></i>
                        </div>
                        <div class="col">
                            <h6 class="mb-0">Shortcut #${index + 1}</h6>
                        </div>
                        <div class="col-auto">
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
                                       name="shortcuts[${index}][icon]"
                                       class="form-control icon-input"
                                       placeholder="ti ti-calendar"
                                       required>
                                <div class="icon-grid" id="icon-grid-${index}">
                                    <!-- Icons will be populated by JavaScript -->
                                </div>
                            </div>
                            <small class="text-muted">Click to select from popular icons</small>
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

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="shortcuts[${index}][open_in_new_tab]"
                                       value="1"
                                       id="new_tab_${index}">
                                <label class="form-check-label" for="new_tab_${index}">
                                    Open in new tab/window
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="shortcuts[${index}][order]" value="${index}">
                            <small class="text-muted">Order: ${index + 1}</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function updateOrder() {
        const shortcutItems = container.querySelectorAll('.shortcut-item');
        shortcutItems.forEach((item, index) => {
            // Update data-index
            item.setAttribute('data-index', index);

            // Update order input
            const orderInput = item.querySelector('input[name*="[order]"]');
            if (orderInput) {
                orderInput.value = index;
            }

            // Update shortcut number
            const shortcutNumber = item.querySelector('h6');
            if (shortcutNumber) {
                shortcutNumber.textContent = `Shortcut #${index + 1}`;
            }

            // Update order display
            const orderDisplay = item.querySelector('.text-muted');
            if (orderDisplay && orderDisplay.textContent.includes('Order:')) {
                orderDisplay.textContent = `Order: ${index + 1}`;
            }
        });
    }


    function initializeIconPickers() {
        // Initialize icon pickers for all icon inputs
        document.querySelectorAll('.icon-input').forEach(input => {
            if (!input.dataset.initialized) {
                input.dataset.initialized = 'true';

                input.addEventListener('click', function() {
                    const gridId = this.parentNode.querySelector('.icon-grid').id;
                    const grid = document.getElementById(gridId);

                    if (grid.style.display === 'none' || grid.style.display === '') {
                        // Show grid and populate with icons
                        grid.style.display = 'block';
                        populateIconGrid(grid, this);
                    } else {
                        grid.style.display = 'none';
                    }
                });

                // Close icon grid when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.icon-picker')) {
                        document.querySelectorAll('.icon-grid').forEach(grid => {
                            grid.style.display = 'none';
                        });
                    }
                });
            }
        });
    }

    function populateIconGrid(grid, input) {
        if (grid.dataset.populated) return;

        grid.innerHTML = '';
        popularIcons.forEach(iconClass => {
            const iconOption = document.createElement('div');
            iconOption.className = 'icon-option';
            iconOption.innerHTML = `<i class="${iconClass}"></i>`;
            iconOption.title = iconClass;

            iconOption.addEventListener('click', function() {
                input.value = iconClass;
                grid.style.display = 'none';
            });

            grid.appendChild(iconOption);
        });

        grid.dataset.populated = 'true';
    }

    // Initial setup
    initializeIconPickers();
});
</script>
@endsection
