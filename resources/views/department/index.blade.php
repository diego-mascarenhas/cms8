@extends('layouts/layoutMaster')

@section('title', __('Departments'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
    <script>
        $(document).on('click', '.btn-delete-department', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({
                title: '{{ __("Are you sure?") }}',
                text: "{{ __("You won't be able to revert this!") }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '{{ __("Yes, delete it!") }}',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-danger ms-1'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Departments') }}</h4>
            <p class="text-muted">{{ __('Define organization departments') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            @can('create', \App\Models\EnterpriseDepartment::class)
            <a href="{{ route('department.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-plus me-1"></i> {{ __('Create department') }}
            </a>
            @endcan
            <a href="{{ route('organization.index') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i> {{ __('Back to organization') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th class="text-center">{{ __('Color') }}</th>
                            @can('viewAny', \App\Models\EnterpriseDepartment::class)
                            <th class="text-center">{{ __('Actions') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td>{{ $department->name }}</td>
                                <td class="text-center">
                                    <span class="badge rounded-pill" style="background-color: {{ $department->color }}; color: #333;">{{ $department->color }}</span>
                                </td>
                                @can('update', $department)
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <a href="{{ route('department.edit', $department) }}" class="text-body" title="{{ __('Edit') }}">
                                            <i class="ti ti-pencil ti-sm me-2"></i>
                                        </a>
                                        @can('delete', $department)
                                        <form action="{{ route('department.destroy', $department) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <a href="#" class="text-danger btn-delete-department" title="{{ __('Delete') }}">
                                                <i class="ti ti-trash ti-sm"></i>
                                            </a>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('viewAny', \App\Models\EnterpriseDepartment::class) ? 3 : 2 }}" class="text-center text-muted">{{ __('No departments yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
