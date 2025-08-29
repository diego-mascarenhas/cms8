@extends('layouts/layoutMaster')

@section('title', 'Team Valorations')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Settings/</span> Valorations</h4>
        <p class="text-muted">Manage contact valorations for your team</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createValorationModal">
            <i class="ti ti-plus me-1"></i> Add Valoration
        </button>
    </div>
</div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>VALORACIÓN</th>
                        <th class="text-center">ICON</th>
                        <th class="text-center">CONTACTOS</th>
                        <th class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($valorations as $valoration)
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <h6 class="mb-0">{{ $valoration->name }}</h6>
                                </div>
                            </td>
                                                    <td class="text-center">
                            {{ $valoration->icon }}
                        </td>
                        <td class="text-center">
                            <span class="badge bg-label-primary">
                                {{ \App\Models\Contact::where('valoration_id', $valoration->id)->count() }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center align-items-center">
                                <a href="javascript:;" class="text-body me-2" onclick="editValoration({{ $valoration->id }}, '{{ $valoration->name }}', '{{ $valoration->icon }}')">
                                    <i class="ti ti-edit ti-sm"></i>
                                </a>
                                <a href="javascript:;" class="text-danger" onclick="deleteValoration({{ $valoration->id }}, '{{ $valoration->name }}')">
                                    <i class="ti ti-trash ti-sm"></i>
                                </a>
                            </div>
                        </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="ti ti-star-off mb-2" style="font-size: 2rem;"></i>
                                <p class="mb-0">No hay valoraciones configuradas</p>
                                <small>Haz clic en "Add Valoration" para crear la primera</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Valoration Modal -->
    <div class="modal fade" id="createValorationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Valoration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('team-settings.valorations.store', $team) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Valoration Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="icon" class="form-label">Icon</label>
                            <select class="form-select" id="icon" name="icon" required>
                                @foreach(\App\Models\ContactValoration::getAvailableIcons() as $iconValue => $iconLabel)
                                    <option value="{{ $iconValue }}">{{ $iconValue }} {{ $iconLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Valoration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Valoration Modal -->
    <div class="modal fade" id="editValorationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Valoration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editValorationForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Valoration Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_icon" class="form-label">Icon</label>
                            <select class="form-select" id="edit_icon" name="icon" required>
                                @foreach(\App\Models\ContactValoration::getAvailableIcons() as $iconValue => $iconLabel)
                                    <option value="{{ $iconValue }}">{{ $iconValue }} {{ $iconLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Valoration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
<script>
    function editValoration(id, name, icon) {
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_icon').value = icon;
        document.getElementById('editValorationForm').action = '{{ route('team-settings.valorations.update', ['team' => $team, 'valoration' => '__ID__']) }}'.replace('__ID__', id);
        new bootstrap.Modal(document.getElementById('editValorationModal')).show();
    }

    function deleteValoration(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to delete the valoration "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-outline-danger'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('team-settings.valorations.destroy', ['team' => $team, 'valoration' => '__ID__']) }}'.replace('__ID__', id);
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                
                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endsection 