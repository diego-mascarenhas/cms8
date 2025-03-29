@extends('layouts/layoutMaster')

@section('title', 'Notas')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
    <script>
        // Display success message if exists
        @if(session('success'))
            toastr.success('{{ session('success') }}');
        @endif

        // Confirmation for delete
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
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

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }

    .post-it {
        background-color: #feff9c;
        padding: 20px;
        margin: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: rotate(-2deg);
        transition: transform 0.3s ease;
        width: 250px;
        min-height: 200px;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .post-it:hover {
        transform: rotate(0deg) scale(1.05);
    }

    .post-it-header {
        font-size: 1.2em;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .post-it-date {
        font-size: 0.8em;
        color: #666;
        margin-bottom: 10px;
    }

    .post-it-content {
        flex-grow: 1;
    }

    .post-it-tag {
        align-self: flex-end;
        font-size: 0.9em;
        color: #007bff;
    }

    .post-it-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: none;
    }

    .post-it:hover .post-it-actions {
        display: flex;
    }

    .post-it-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        margin-left: 0.25rem;
    }
</style>

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">Organización</h4>
            <p class="text-muted">Organización por departamentos</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('organization.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Create New Task
            </a>
        </div>
    </div>

    @foreach ($departmentPostits as $departmentName => $postits)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ $departmentName }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap">
                    @foreach ($postits as $postit)
                        <div class="post-it" style="background-color: {{ $postit['color'] }};">
                            <div class="post-it-header">{{ $postit['header'] }}</div>
                            <div class="post-it-date">{{ $postit['author'] }}</div>
                            <div class="post-it-content">
                                {{ $postit['content'] }}
                            </div>
                            <div class="post-it-tag">
                                {{ $postit['time_allocation'] }}
                                @if (!empty($postit['availability']))
                                    ({{ $postit['availability'] }})
                                @endif
                            </div>
                            
                            <div class="post-it-actions">
                                <a href="{{ route('organization.edit', $postit['id']) }}" class="btn btn-sm btn-icon btn-primary">
                                    <i class="ti ti-pencil"></i>
                                </a>
                                <form action="{{ route('organization.destroy', $postit['id']) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-icon btn-danger btn-delete">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

@endsection

{{-- vendor scripts --}}
@section('vendor-script')
    <script src="{{ asset('vendors/data-tables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
    <script src="{{ asset('vendors/fullcalendar/lib/moment.min.js') }}"></script>
    <script src="{{ asset('js/moment/' . app()->getLocale() . '.js') }}"></script>
@endsection
