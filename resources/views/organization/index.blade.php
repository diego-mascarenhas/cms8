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
</style>

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">Organización</h4>
            <p class="text-muted">Organización por departamentos</p>
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
