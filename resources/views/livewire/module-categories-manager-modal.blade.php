<div class="d-inline-flex align-items-center lh-1">
    <button type="button" class="btn btn-icon btn-text-secondary border-0 shadow-none p-0" style="width: 1.25rem; height: 1.25rem;" wire:click="openModal" title="{{ __('Manage categories') }}" aria-label="{{ __('Manage categories') }}" data-bs-toggle="tooltip">
        <i class="ti ti-settings" style="font-size: 1rem; line-height: 1;"></i>
    </button>

    @if ($show)
        <style>
            .table-module-categories-manager-modal tbody tr:last-child td {
                border-bottom-width: 0;
            }
        </style>
        @teleport('body')
            <div
                class="modal fade show"
                style="display: block; background-color: rgba(0,0,0,0.5); z-index: 1070;"
                tabindex="-1"
                wire:click.self="closeModal"
                wire:keydown.escape.window="closeModal"
            >
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" wire:click.stop>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Manage categories') }}</h5>
                            <button type="button" class="btn-close" wire:click="closeModal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            @if ($feedback)
                                <div @class(['alert mb-3', 'alert-success' => $feedbackType === 'success', 'alert-danger' => $feedbackType !== 'success'])>
                                    {{ $feedback }}
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-module-categories-manager-modal">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Name') }}</th>
                                            <th class="text-end">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->rows as $row)
                                            <tr wire:key="cat-row-{{ $row['id'] }}">
                                                <td>
                                                    @if ($editingId === $row['id'])
                                                        <input type="text" class="form-control form-control-sm" wire:model.live="editingName" wire:keydown.enter.prevent="saveEdit">
                                                    @else
                                                        {{ $row['display'] }}
                                                        @unless ($row['can_manage'])
                                                            <span class="badge bg-label-secondary ms-1">{{ __('Global') }}</span>
                                                        @endunless
                                                    @endif
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    @if ($row['can_manage'])
                                                        @if ($editingId === $row['id'])
                                                            <button type="button" class="btn btn-sm btn-primary" wire:click="saveEdit">{{ __('Save') }}</button>
                                                            <button type="button" class="btn btn-sm btn-label-secondary" wire:click="cancelEdit">{{ __('Cancel') }}</button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none" title="{{ __('Edit') }}" wire:click="startEdit({{ $row['id'] }})">
                                                                <i class="ti ti-edit"></i>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-icon btn-text-danger border-0 shadow-none"
                                                                title="{{ __('Delete') }}"
                                                                wire:click="deleteCategory({{ $row['id'] }})"
                                                                wire:confirm="{{ __('Are you sure?') }}"
                                                            >
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-muted">{{ __('No records found') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
