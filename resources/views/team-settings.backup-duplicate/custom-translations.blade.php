@extends('layouts/layoutMaster')

@section('title', 'Custom Translations')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Custom Translations</h4>
        <p class="text-muted">Manage custom translations for your team</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
            <i class="ti ti-plus me-1"></i>Add Translation
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <!-- Usage Examples -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ti ti-info-circle me-2"></i>How to Use Custom Translations
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Example Usage:</h6>
                        <div class="bg-light p-3 rounded mb-3">
                            <p class="mb-2"><strong>Instead of:</strong></p>
                            <code class="text-danger">¡Bienvenido a bbo! 👋</code>
                            <small class="text-muted d-block">(shows: "¡Bienvenido a bbo! 👋")</small>
                        </div>
                        <div class="bg-light p-3 rounded">
                            <p class="mb-2"><strong>Use:</strong></p>
                            <code class="text-success">Bienvenida!</code>
                            <small class="text-muted d-block">(shows: "¡Bienvenida a bbo! 👋" if custom translation exists)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">How to configure:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <span class="text-info"><strong>Key:</strong></span>
                                <code class="bg-light px-2 py-1 rounded">welcome</code>
                            </li>
                            <li class="mb-2">
                                <span class="text-info"><strong>Group:</strong></span>
                                <code class="bg-light px-2 py-1 rounded">auth</code>
                            </li>
                            <li class="mb-2">
                                <span class="text-info"><strong>Locale:</strong></span>
                                <code class="bg-light px-2 py-1 rounded">es</code>
                            </li>
                            <li class="mb-2">
                                <span class="text-info"><strong>Value:</strong></span>
                                <code class="bg-light px-2 py-1 rounded">¡Bienvenida a :name! 👋</code>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary">Common Translation Keys:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Key</th>
                                        <th>Group</th>
                                        <th>Description</th>
                                        <th>Example Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>welcome</code></td>
                                        <td><span class="badge bg-label-primary">auth</span></td>
                                        <td>Login page welcome message</td>
                                        <td><code>¡Bienvenida a :name! 👋</code></td>
                                    </tr>
                                    <tr>
                                        <td><code>login.description</code></td>
                                        <td><span class="badge bg-label-primary">auth</span></td>
                                        <td>Login page description</td>
                                        <td><code>Accede a tu cuenta personalizada</code></td>
                                    </tr>
                                    <tr>
                                        <td><code>dashboard.title</code></td>
                                        <td><span class="badge bg-label-secondary">app</span></td>
                                        <td>Dashboard page title</td>
                                        <td><code>Panel de Control Personalizado</code></td>
                                    </tr>
                                    <tr>
                                        <td><code>projects.title</code></td>
                                        <td><span class="badge bg-label-secondary">app</span></td>
                                        <td>Projects page title</td>
                                        <td><code>Mis Proyectos Especiales</code></td>
                                    </tr>
                                    <tr>
                                        <td><code>required</code></td>
                                        <td><span class="badge bg-label-warning">validation</span></td>
                                        <td>Validation required message</td>
                                        <td><code>Este campo es obligatorio</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($translations->count() > 0)
            @foreach($translations as $group => $groupTranslations)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ $availableGroups[$group] ?? ucfirst($group) }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Key</th>
                                    <th>Value</th>
                                    <th>Locale</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupTranslations as $translation)
                                    <tr>
                                        <td><code>{{ $translation->key }}</code></td>
                                        <td>{{ Str::limit($translation->value, 100) }}</td>
                                        <td>
                                            <span class="badge bg-label-primary">{{ $availableLocales[$translation->locale] ?? $translation->locale }}</span>
                                        </td>
                                                                                                                        <td>
                                            <div class="d-inline-flex">
                                                <a href="javascript:;" class="text-body me-1"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#editTranslationModal"
                                                   data-translation="{{ $translation->id }}"
                                                   data-key="{{ $translation->key }}"
                                                   data-value="{{ $translation->value }}"
                                                   data-group="{{ $translation->group }}"
                                                   data-locale="{{ $translation->locale }}">
                                                    <i class="ti ti-edit ti-sm me-2"></i>
                                                </a>
                                                <form action="{{ route('team-settings.custom-translations.destroy', ['team' => $team, 'translation' => $translation]) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('Are you sure you want to delete this translation?')"
                                                      class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-danger border-0 bg-transparent p-0">
                                                        <i class="ti ti-trash ti-sm"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @else
            <div class="card">
                <div class="card-body text-center">
                    <i class="ti ti-language mb-3" style="font-size: 3rem; color: #ccc;"></i>
                    <h5>No custom translations found</h5>
                    <p class="text-muted">Start by adding your first custom translation</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
                        <i class="ti ti-plus me-1"></i>Add Translation
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Add Translation Modal -->
<div class="modal fade" id="addTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('team-settings.custom-translations.store', $team) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Custom Translation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="key" class="form-label">Translation Key</label>
                        <input type="text" class="form-control" id="key" name="key" required>
                        <div class="form-text">e.g., welcome, login.description, dashboard.title</div>
                    </div>

                    <div class="mb-3">
                        <label for="value" class="form-label">Translation Value</label>
                        <textarea class="form-control" id="value" name="value" rows="3" required></textarea>
                        <div class="form-text">You can use :name, :email, etc. for dynamic values</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="group" class="form-label">Group</label>
                                <select class="form-select" id="group" name="group" required>
                                    @foreach($availableGroups as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="locale" class="form-label">Locale</label>
                                <select class="form-select" id="locale" name="locale" required>
                                    @foreach($availableLocales as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Translation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Translation Modal -->
<div class="modal fade" id="editTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTranslationForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Custom Translation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_key" class="form-label">Translation Key</label>
                        <input type="text" class="form-control" id="edit_key" name="key" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_value" class="form-label">Translation Value</label>
                        <textarea class="form-control" id="edit_value" name="value" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_group" class="form-label">Group</label>
                                <select class="form-select" id="edit_group" name="group" required>
                                    @foreach($availableGroups as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_locale" class="form-label">Locale</label>
                                <select class="form-select" id="edit_locale" name="locale" required>
                                    @foreach($availableLocales as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Translation</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit translation modal
    const editModal = document.getElementById('editTranslationModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const translationId = button.getAttribute('data-translation');
            const key = button.getAttribute('data-key');
            const value = button.getAttribute('data-value');
            const group = button.getAttribute('data-group');
            const locale = button.getAttribute('data-locale');

            // Update form action
            const form = document.getElementById('editTranslationForm');
            form.action = `{{ route('team-settings.custom-translations.update', ['team' => $team, 'translation' => ':id']) }}`.replace(':id', translationId);

            // Update form fields
            document.getElementById('edit_key').value = key;
            document.getElementById('edit_value').value = value;
            document.getElementById('edit_group').value = group;
            document.getElementById('edit_locale').value = locale;
        });
    }
});
</script>
@endsection
