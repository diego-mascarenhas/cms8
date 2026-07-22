@extends('layouts/layoutMaster')

@section('title', __('Payments'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Payments') }}</h4>
        <p class="text-muted">{{ __('Manage your payments') }}</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
        <a href="{{ route('payments.syncs.mercadopago.index') }}" class="btn btn-label-info">
            <i class="ti ti-wallet me-1"></i>{{ __('payment_sync.mercadopago.open_queue') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<input type="hidden" id="payment-status-filter" value="all">

<div class="card mb-4">
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
            <div class="row gy-4 gy-sm-1">
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-3 pb-sm-0">
                        <div>
                            <h6 class="mb-2">{{ __('In Process') }}</h6>
                            <h4 class="mb-0">{{ number_format($paymentSummary['in_process_count']) }}</h4>
                        </div>
                        <span class="avatar me-sm-4">
                            <a
                                href="#"
                                class="avatar-initial bg-label-primary rounded payment-summary-filter"
                                data-status-filter="1"
                                title="{{ __('Filter by :status', ['status' => __('In Process')]) }}"
                            >
                                <i class="ti ti-loader ti-md"></i>
                            </a>
                        </span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none me-4">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-3 pb-sm-0">
                        <div>
                            <h6 class="mb-2">{{ __('Pending') }}</h6>
                            <h4 class="mb-0">{{ number_format($paymentSummary['pending_count']) }}</h4>
                        </div>
                        <span class="avatar me-sm-4">
                            <a
                                href="#"
                                class="avatar-initial bg-label-warning rounded payment-summary-filter"
                                data-status-filter="3"
                                title="{{ __('Filter by :status', ['status' => __('Pending')]) }}"
                            >
                                <i class="ti ti-clock ti-md"></i>
                            </a>
                        </span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-3 border-end pb-3 pb-sm-0">
                        <div>
                            <h6 class="mb-2">{{ __('Approved') }}</h6>
                            <h4 class="mb-0">{{ number_format($paymentSummary['approved_count']) }}</h4>
                        </div>
                        <span class="avatar me-sm-4">
                            <a
                                href="#"
                                class="avatar-initial bg-label-success rounded payment-summary-filter"
                                data-status-filter="2"
                                title="{{ __('Filter by :status', ['status' => __('Approved')]) }}"
                            >
                                <i class="ti ti-circle-check ti-md"></i>
                            </a>
                        </span>
                    </div>
                    <hr class="d-none d-sm-block d-lg-none me-4">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex justify-content-between align-items-start card-widget-4 pb-3 pb-sm-0">
                        <div>
                            <h6 class="mb-2">{{ __('Failed') }}</h6>
                            <h4 class="mb-0">{{ number_format($paymentSummary['failed_count']) }}</h4>
                        </div>
                        <span class="avatar me-sm-4">
                            <a
                                href="#"
                                class="avatar-initial bg-label-danger rounded payment-summary-filter"
                                data-status-filter="failed"
                                title="{{ __('Filter by :status', ['status' => __('Failed')]) }}"
                            >
                                <i class="ti ti-alert-triangle ti-md"></i>
                            </a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-style')
<style>
    .payment-summary-filter {
        cursor: pointer;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .payment-summary-filter:hover {
        transform: scale(1.05);
    }

    .payment-summary-filter.active-filter {
        transform: scale(1.1);
        box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.25);
    }
</style>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
<script>
    $(function () {
        var $filter = $('#payment-status-filter');

        function applyPaymentSummaryFilter(filter, $icon) {
            if ($filter.val() === String(filter)) {
                $filter.val('all');
                $('.payment-summary-filter').removeClass('active-filter');
            } else {
                $filter.val(filter);
                $('.payment-summary-filter').removeClass('active-filter');
                $icon.addClass('active-filter');
            }

            if (window.LaravelDataTables && window.LaravelDataTables['payment-table']) {
                window.LaravelDataTables['payment-table'].ajax.reload();
            }
        }

        $('.payment-summary-filter').on('click', function (e) {
            e.preventDefault();
            applyPaymentSummaryFilter($(this).data('status-filter'), $(this));
        });
    });
</script>
@endsection
