@extends('layouts/layoutMaster')

@section('title', __('Tasks') . ' - Kanban')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/jkanban/jkanban.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-kanban.css')}}" />
<style>
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jkanban/jkanban.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/app-kanban.js') }}"></script>
@endsection

@section('content')
<div class="app-kanban">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Tasks') }} - {{ __('Kanban Board') }}</h4>
            <p class="text-muted">{{ __('Manage tasks visually') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
            <button type="button" class="btn btn-primary kanban-add-board-btn">
                <i class="ti ti-plus me-1"></i>{{ __('Add Board') }}
            </button>
            <button type="button" class="btn btn-label-secondary" id="view-list-btn">
                <i class="ti ti-list me-1"></i>{{ __('List View') }}
            </button>
        </div>
    </div>

    <!-- Kanban Container & Wrapper -->
    <div class="kanban-container">
        <div class="kanban-wrapper"></div>
    </div>

    <!-- Add New Board (template original) -->
    <form class="kanban-add-new-board d-none">
        <div class="mb-3">
            <input type="text" class="form-control kanban-add-board-input d-none" placeholder="{{ __('Board Title') }}">
        </div>
        <div class="mb-3">
            <button type="submit" class="btn btn-primary me-2 waves-effect waves-light">{{ __('Add') }}</button>
            <button type="button" class="btn btn-label-secondary kanban-add-board-cancel-btn waves-effect waves-light">{{ __('Cancel') }}</button>
        </div>
    </form>

    <!-- Edit Task Sidebar (template original) -->
    <div class="offcanvas offcanvas-end kanban-update-item-sidebar">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">{{ __('Edit Task') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label" for="title">{{ __('Title') }}</label>
                <input type="text" id="title" class="form-control" placeholder="{{ __('Task title') }}" />
            </div>
            <div class="mb-3">
                <label class="form-label" for="due-date">{{ __('Due Date') }}</label>
                <input type="text" id="due-date" class="form-control" placeholder="{{ __('Select date') }}" />
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Labels') }}</label>
                <select class="select2 form-select" multiple>
                    <option data-color="bg-label-primary">{{ __('New') }}</option>
                    <option data-color="bg-label-success">{{ __('In Progress') }}</option>
                    <option data-color="bg-label-danger">{{ __('Blocked') }}</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Assigned') }}</label>
                <div class="assigned d-flex align-items-center"></div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Comments') }}</label>
                <div class="comment-toolbar border-bottom pb-1"></div>
                <div class="comment-editor"></div>
            </div>
        </div>
    </div>
</div>
@endsection
