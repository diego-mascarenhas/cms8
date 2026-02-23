@extends('layouts/layoutMaster')

@section('title', __('Users'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<!-- Header following project pattern -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Users') }}</h4>
        <p class="text-muted">{{ __('Manage team users and their permissions') }}</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Users') }}</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2">{{$totalUser}}</h3>
                            <small class="text-success">(100%)</small>
                        </div>
                        <small>{{ __('Total Users') }}</small>
                    </div>
                    <span class="badge bg-label-primary rounded p-2">
                        <i class="ti ti-user ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Verified Users') }}</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2">{{$verified}}</h3>
                            <small class="text-success">({{ $totalUser > 0 ? round(($verified / $totalUser) * 100) : 0 }}%)</small>
                        </div>
                        <small>{{ __('Email verified') }}</small>
                    </div>
                    <span class="badge bg-label-success rounded p-2">
                        <i class="ti ti-user-check ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Duplicate Users') }}</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2">{{$userDuplicates}}</h3>
                            <small class="text-warning">({{ $totalUser > 0 ? round(($userDuplicates / $totalUser) * 100) : 0 }}%)</small>
                        </div>
                        <small>{{ __('Duplicate emails') }}</small>
                    </div>
                    <span class="badge bg-label-danger rounded p-2">
                        <i class="ti ti-users ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Verification Pending') }}</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2">{{$notVerified}}</h3>
                            <small class="text-danger">({{ $totalUser > 0 ? round(($notVerified / $totalUser) * 100) : 0 }}%)</small>
                        </div>
                        <small>{{ __('Pending verification') }}</small>
                    </div>
                    <span class="badge bg-label-warning rounded p-2">
                        <i class="ti ti-user-circle ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users DataTable -->
<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>

<!-- Offcanvas to add new user -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAddUser" aria-labelledby="offcanvasAddUserLabel">
    <div class="offcanvas-header">
        <h5 id="offcanvasAddUserLabel" class="offcanvas-title">{{ __('Add User') }}</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0">
        <form class="add-new-user pt-0" id="addNewUserForm">
            <input type="hidden" name="id" id="user_id">
            <div class="mb-3">
                <label class="form-label" for="add-user-fullname">{{ __('Full Name') }}</label>
                <input type="text" class="form-control" id="add-user-fullname" name="name" />
            </div>
            <div class="mb-3">
                <label class="form-label" for="add-user-email">{{ __('Email') }}</label>
                <input type="text" id="add-user-email" class="form-control" name="email" />
            </div>
            <div class="mb-3">
                <label class="form-label" for="add-user-contact">{{ __('Phone') }}</label>
                <input type="text" id="add-user-contact" class="form-control" name="userContact" />
            </div>
            <div class="mb-3">
                <label class="form-label" for="user-role">{{ __('Role') }}</label>
                <select id="user-role" name="role" class="form-select">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $role->name == 'guest' ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary me-sm-3 me-1 data-submit">{{ __('Submit') }}</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="offcanvas">{{ __('Cancel') }}</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

    <script>
        function deleteUser(id, element) {
            event.preventDefault();
            Swal.fire({
                title: '{{ __("Are you sure?") }}',
                text: "{{ __('Do you want to delete this user?') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '{{ __("Yes, delete") }}',
                cancelButtonText: '{{ __("Cancel") }}',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.value) {
                    fetch("{{ route('user.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        const row = element.closest('tr');
                        if (row) {
                            row.classList.add('fade-out');
                            row.addEventListener('transitionend', () => {
                                row.remove();
                            });
                        }

                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("Success!") }}',
                            text: data.success,
                            customClass: {
                                confirmButton: 'btn btn-success'
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("Error") }}',
                            text: '{{ __("An error occurred while deleting the record") }}',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        });
                    });
                }
            });
        }

        $(document).ready(function() {
            // Edit user handler
            $(document).on('click', '.edit-user', function() {
                var id = $(this).data('id');
                // Add edit functionality here
                console.log('Edit user:', id);
            });
        });
    </script>
@endpush

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }
</style>
