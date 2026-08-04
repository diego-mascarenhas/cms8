@extends('layouts/layoutMaster')

@section('title', __('stripe_subscription.title'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('stripe_subscription.title') }}</h4>
        <p class="text-muted">{{ __('stripe_subscription.subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <form action="{{ route('subscription.sync') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-refresh me-1"></i>{{ __('stripe_subscription.sync_button') }}
            </button>
        </form>
    </div>
</div>

@php
    $statusIcons = [
        'active' => ['icon' => 'ti-circle-check', 'bg' => 'bg-label-success', 'text' => 'text-success'],
        'trialing' => ['icon' => 'ti-hourglass', 'bg' => 'bg-label-info', 'text' => 'text-info'],
        'past_due' => ['icon' => 'ti-alert-triangle', 'bg' => 'bg-label-warning', 'text' => 'text-warning'],
        'unpaid' => ['icon' => 'ti-credit-card-off', 'bg' => 'bg-label-danger', 'text' => 'text-danger'],
        'incomplete' => ['icon' => 'ti-progress', 'bg' => 'bg-label-dark', 'text' => 'text-dark'],
        'incomplete_expired' => ['icon' => 'ti-clock-x', 'bg' => 'bg-label-secondary', 'text' => 'text-secondary'],
        'canceled' => ['icon' => 'ti-circle-x', 'bg' => 'bg-label-danger', 'text' => 'text-danger'],
        'paused' => ['icon' => 'ti-player-pause', 'bg' => 'bg-label-secondary', 'text' => 'text-secondary'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach(($subscriptionStatuses ?? []) as $statusKey)
        @php
            $iconMeta = $statusIcons[$statusKey] ?? ['icon' => 'ti-circle', 'bg' => 'bg-label-primary', 'text' => 'text-primary'];
            $isSelected = ($selectedStatus ?? '') === $statusKey;
        @endphp
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="{{ route('subscription.index', ['status' => $statusKey]) }}" class="text-decoration-none text-body">
            <div class="card h-100 {{ $isSelected ? 'border border-primary' : '' }}">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-muted small">{{ __('stripe_subscription.status.'.$statusKey) }}</span>
                        <span class="avatar avatar-sm">
                            <span class="avatar-initial rounded {{ $iconMeta['bg'] }}">
                                <i class="ti {{ $iconMeta['icon'] }} {{ $iconMeta['text'] }}"></i>
                            </span>
                        </span>
                    </div>
                    <h4 class="mb-0 mt-2">{{ number_format((int) ($statusCounts[$statusKey] ?? 0)) }}</h4>
                </div>
            </div>
            </a>
        </div>
    @endforeach
</div>
@if(! empty($selectedStatus))
    <div class="mb-3">
        <a href="{{ route('subscription.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-filter-off me-1"></i>{{ __('Quitar filtro') }}
        </a>
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>

@if (! empty($canEditCategory))
    @include('partials.line-category-modal', [
        'categoryOptions' => $categoryOptions ?? [],
        'showSuggestion' => false,
        'livewireKey' => 'subscription-cat-mgr-services',
    ])
@endif
@endsection

@section('page-script')
@if (! empty($canEditCategory))
<script>
$(function () {
    var updateUrlTemplate = @json(url('/subscription/stripe/__ID__/service-category'));
    var createUrlTemplate = @json(url('/subscription/stripe/__ID__/create-service'));
    var uncategorizedLabel = @json(__('Uncategorized'));
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var $modal = $('#lineCategoryModal');
    var $select = $('#line_category_modal_select');
    var mode = 'update';
    var subscriptionId = null;

    function updateUrl(id) {
        return updateUrlTemplate.replace('__ID__', String(id));
    }

    function createUrl(id) {
        return createUrlTemplate.replace('__ID__', String(id));
    }

    function initSelect() {
        if (! $select.length) {
            return;
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            dropdownParent: $modal,
            width: '100%',
            allowClear: true,
            placeholder: uncategorizedLabel,
        });
    }

    function reloadTable() {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#stripe-subscription-table')) {
            $('#stripe-subscription-table').DataTable().ajax.reload(null, false);
        }
    }

    function persistCategory(categoryId) {
        if (! subscriptionId) {
            return;
        }

        var $saveBtn = $('#save-line-category');
        var $clearBtn = $('#clear-line-category');
        $saveBtn.prop('disabled', true);
        $clearBtn.prop('disabled', true);

        var isCreate = mode === 'create';
        var payload = {
            _token: csrfToken,
            category_id: categoryId === '' || categoryId === null ? null : categoryId,
        };

        if (! isCreate) {
            payload._method = 'PATCH';
        }

        $.ajax({
            url: isCreate ? createUrl(subscriptionId) : updateUrl(subscriptionId),
            type: 'POST',
            data: payload,
            success: function () {
                bootstrap.Modal.getOrCreateInstance($modal.get(0)).hide();
                reloadTable();
            },
            error: function (xhr) {
                var message = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : @json(__('Could not update category.'));
                alert(message);
            },
            complete: function () {
                $saveBtn.prop('disabled', false);
                $clearBtn.prop('disabled', false);
            },
        });
    }

    $(document).on('click', '.subscription-category-badge', function () {
        mode = 'update';
        subscriptionId = $(this).data('subscription-id');
        var currentId = $.trim(String($(this).data('category-id') || ''));
        initSelect();
        $select.val(currentId).trigger('change');
        bootstrap.Modal.getOrCreateInstance($modal.get(0)).show();
    });

    $(document).on('click', '.create-subscription-service', function (e) {
        e.preventDefault();
        mode = 'create';
        subscriptionId = $(this).data('subscription-id');
        initSelect();
        $select.val('').trigger('change');
        bootstrap.Modal.getOrCreateInstance($modal.get(0)).show();
    });

    if ($modal.length) {
        initSelect();
        $modal.on('shown.bs.modal', function () {
            initSelect();
        });

        $('#save-line-category').on('click', function () {
            persistCategory($.trim(String($select.val() || '')));
        });

        $('#clear-line-category').on('click', function () {
            persistCategory(null);
        });

        if (typeof Livewire !== 'undefined' && typeof Livewire.on === 'function') {
            Livewire.on('module-categories-refreshed', function () {
                initSelect();
            });
        }
    }
});
</script>
@endif
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
