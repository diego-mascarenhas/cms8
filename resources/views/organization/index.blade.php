@extends('layouts/layoutMaster')

@section('title', __('Organización'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                toastr.success('{{ session('success') }}');
            @endif
        });

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

        function openContentModal(title, content) {
            content = content.replace(/\\n/g, '<br>');
            Swal.fire({
                title: title,
                html: '<div class="text-start">' + content + '</div>',
                width: '600px',
                showCloseButton: false,
                confirmButtonText: 'Cerrar',
                customClass: {
                    confirmButton: 'btn btn-secondary',
                    popup: 'swal2-modal-custom'
                },
                buttonsStyling: false,
                padding: '1em 2em 2em 2em'
            });
        }
    </script>
@endsection

@section('page-style')
<style>
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
    .post-it-header { font-size: 1.2em; font-weight: bold; margin-bottom: 10px; }
    .post-it-date { font-size: 0.8em; color: #666; margin-bottom: 10px; }
    .post-it-content { flex-grow: 1; }
    .post-it-tag { align-self: flex-end; font-size: 0.9em; color: #007bff; }
    .post-it-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: none;
        gap: 8px;
    }
    .post-it:hover .post-it-actions { display: flex; }
    .post-it-actions i { font-size: 16px; color: #666; cursor: pointer; transition: color 0.2s ease; }
    .post-it-actions i:hover { color: #333; }
    .post-it-actions form { margin: 0; padding: 0; display: inline; }
    .swal2-modal-custom { padding-top: 1em !important; }
    .swal2-modal-custom .swal2-title { margin-top: 0; padding-top: 0; }
    .organization-post-it.sortable-ghost { opacity: 0.4; }
    .organization-post-it.sortable-chosen { transform: rotate(0deg) scale(1.02); }
</style>
@endsection

@section('content')
    @livewire('organization-board')
@endsection

@push('scripts')
<script>
    function getOrderedIds(container) {
        var ids = [];
        if (!container) return ids;
        container.querySelectorAll('.organization-post-it[data-id]').forEach(function(el) {
            var id = parseInt(el.getAttribute('data-id'), 10);
            if (!isNaN(id)) ids.push(id);
        });
        return ids;
    }

    function getDepartmentId(container) {
        if (!container) return null;
        var id = parseInt(container.getAttribute('data-department-id'), 10);
        return isNaN(id) ? null : id;
    }

    function initOrganizationSortables() {
        document.querySelectorAll('.organization-postits-container').forEach(function(container) {
            if (container.dataset.sortableInited === '1') return;
            var departmentId = getDepartmentId(container);
            if (departmentId === null) return;
            container.dataset.sortableInited = '1';
            Sortable.create(container, {
                group: 'organization-departments',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
                    var toEl = evt.to;
                    var fromEl = evt.from;
                    var toDeptId = getDepartmentId(toEl);
                    var fromDeptId = getDepartmentId(fromEl);
                    var idsInTo = getOrderedIds(toEl);
                    var idsInFrom = getOrderedIds(fromEl);
                    var movedId = evt.item ? parseInt(evt.item.getAttribute('data-id'), 10) : null;
                    if (typeof Livewire === 'undefined' || !idsInTo.length) return;
                    var wireEl = toEl.closest('[wire\\:id]');
                    if (!wireEl) return;
                    var component = Livewire.find(wireEl.getAttribute('wire:id'));
                    if (!component) return;
                    if (fromDeptId !== toDeptId && movedId) {
                        component.call('moveToDepartment', movedId, toDeptId, idsInTo, fromDeptId, idsInFrom);
                    } else {
                        component.call('reorder', toDeptId, idsInTo);
                    }
                }
            });
        });
    }
    document.addEventListener('livewire:init', function() {
        setTimeout(initOrganizationSortables, 50);
    });
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initOrganizationSortables, 100);
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('morph.updated', function() {
                initOrganizationSortables();
            });
        }
    });
</script>
@endpush
