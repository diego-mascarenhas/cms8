@extends('layouts/layoutMaster')

@section('title', __('Orders'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Orders') }}</h4>
            <p class="text-muted">{{ __('Orders from your WooCommerce store') }}</p>
        </div>
        <div class="mt-3 mt-md-0">
            {{-- Orders are created via WooCommerce checkout; edit only from CMS --}}
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if (count($orders) === 0)
                <p class="text-muted mb-0">{{ __('No orders found.') }} {{ __('Manage your store in') }} <a href="{{ $storeUrl }}/wp-admin/edit.php?post_type=shop_order" target="_blank" rel="noopener noreferrer">{{ __('WooCommerce') }}</a></p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Order') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th class="text-center">{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @php
                                    $billing = $order['billing'] ?? [];
                                    $customerName = trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? ''));
                                    if ($customerName === '') {
                                        $customerName = $billing['email'] ?? __('Guest');
                                    }
                                @endphp
                                <tr>
                                    <td>#{{ $order['number'] ?? $order['id'] ?? '—' }}</td>
                                    <td>{{ isset($order['date_created']) ? \Carbon\Carbon::parse($order['date_created'])->format('d/m/Y H:i') : '—' }}</td>
                                    <td>{{ $customerName }}</td>
                                    <td class="text-center">
                                        @php $status = $order['status'] ?? ''; @endphp
                                        @if ($status === 'processing')
                                            <span class="badge bg-primary">{{ __('Processing') }}</span>
                                        @elseif ($status === 'completed')
                                            <span class="badge bg-success">{{ __('Completed') }}</span>
                                        @elseif ($status === 'pending')
                                            <span class="badge bg-warning">{{ __('Pending') }}</span>
                                        @elseif ($status === 'cancelled')
                                            <span class="badge bg-secondary">{{ __('Cancelled') }}</span>
                                        @else
                                            <span class="badge bg-label-secondary">{{ $status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $order['total'] ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-center align-items-center">
                                            @can('update', new \App\Models\Order(['team_id' => auth()->user()->currentTeam?->id]))
                                                <a href="{{ route('order.edit', $order['id']) }}" class="text-body" title="{{ __('Edit') }}">
                                                    <i class="ti ti-edit ti-sm me-2"></i>
                                                </a>
                                            @endcan
                                            <a href="{{ $storeUrl }}/wp-admin/post.php?post={{ $order['id'] }}&action=edit" target="_blank" rel="noopener noreferrer" class="text-body" title="{{ __('View in WooCommerce') }}">
                                                <i class="ti ti-eye ti-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
