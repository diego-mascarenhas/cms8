@extends('layouts/layoutMaster')

@section('title', 'Custom Translations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Custom Translations</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
                        <i class="ti ti-plus me-1"></i>Add Translation
                    </button>
                </div>
                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Example Section -->
                    <div class="alert alert-info">
                        <h6>Example Usage:</h6>
                        <p>Instead of: <code>{{ __('auth.login.welcome', ['name' => config('variables.templateName')]) }}</code> (shows: "¡Bienvenido a bbo! 👋")</p>
                        <p>Use: <code>{{ \App\Helpers\TranslationHelper::transGroup('welcome', 'auth', ['name' => config('variables.templateName')]) }}</code> (shows: "¡Bienvenida a bbo! 👋" if custom translation exists)</p>
                        <hr>
                        <h6>How to configure:</h6>
                        <ul>
                            <li><strong>Key:</strong> <code>welcome</code></li>
                            <li><strong>Group:</strong> <code>auth</code></li>
                            <li><strong>Locale:</strong> <code>es</code></li>
                            <li><strong>Value:</strong> <code>¡Bienvenida a :name! 👋</code></li>
                        </ul>
                    </div>

                    <!-- Current Translations Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Key</th>
                                    <th>Group</th>
                                    <th>Locale</th>
                                    <th>Custom Value</th>
                                    <th>Default Value</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($translations as $translation)
                                    <tr>
                                        <td><code>{{ $translation->key }}</code></td>
                                        <td>{{ $translation->group }}</td>
                                        <td>{{ $translation->locale }}</td>
                                        <td>{{ $translation->value }}</td>
                                        <td><small class="text-muted">{{ __($translation->key) }}</small></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editTranslationModal{{ $translation->id }}">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                            <form action="{{ route('custom-translations.destroy', $translation->id) }}"
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Are you sure?')">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No custom translations found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Translation Modal -->
<div class="modal fade" id="addTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('custom-translations.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Custom Translation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="key" class="form-label">Translation Key</label>
                        <input type="text" class="form-control" id="key" name="key" required
                               placeholder="e.g., welcome, save, cancel">
                        <small class="form-text text-muted">The key used in your code (e.g., __('welcome'))</small>
                    </div>

                    <div class="mb-3">
                        <label for="group" class="form-label">Group</label>
                        <select class="form-select" id="group" name="group" required>
                            <option value="app">App</option>
                            <option value="validation">Validation</option>
                            <option value="auth">Auth</option>
                            <option value="passwords">Passwords</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="locale" class="form-label">Locale</label>
                        <select class="form-select" id="locale" name="locale" required>
                            <option value="es" {{ $currentLocale === 'es' ? 'selected' : '' }}>Spanish (es)</option>
                            <option value="en" {{ $currentLocale === 'en' ? 'selected' : '' }}>English (en)</option>
                            <option value="fr" {{ $currentLocale === 'fr' ? 'selected' : '' }}>French (fr)</option>
                            <option value="de" {{ $currentLocale === 'de' ? 'selected' : '' }}>German (de)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="value" class="form-label">Custom Value</label>
                        <textarea class="form-control" id="value" name="value" rows="3" required
                                  placeholder="Enter your custom translation"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Translation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Translation Modals -->
@foreach($translations as $translation)
<div class="modal fade" id="editTranslationModal{{ $translation->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('custom-translations.update', $translation->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Translation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Key</label>
                        <input type="text" class="form-control" value="{{ $translation->key }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Group</label>
                        <input type="text" class="form-control" value="{{ $translation->group }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Locale</label>
                        <input type="text" class="form-control" value="{{ $translation->locale }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="value{{ $translation->id }}" class="form-label">Custom Value</label>
                        <textarea class="form-control" id="value{{ $translation->id }}" name="value" rows="3" required>{{ $translation->value }}</textarea>
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
@endforeach
@endsection
