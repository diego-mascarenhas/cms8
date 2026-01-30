@extends('layouts/layoutMaster')

@section('title', __('Products'))

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
            <h4 class="mb-1 mt-3">{{ __('Products') }}</h4>
            <p class="text-muted">{{ __('Products from your WooCommerce store') }}</p>
        </div>
        <div class="mt-3 mt-md-0">
            @can('product.create')
                <a href="{{ route('product.create') }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i> {{ __('Create product') }}
                </a>
            @endcan
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
            @if (count($products) === 0)
                <p class="text-muted mb-0">{{ __('No products found.') }} {{ __('Configure your store in') }} <a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer">{{ $storeUrl }}</a></p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th class="text-center">{{ __('Price') }}</th>
                                <th class="text-center">{{ __('Status') }}</th>
                                <th class="text-center">{{ __('Stock') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td>
                                        @if (!empty($product['permalink']))
                                            <a href="{{ $product['permalink'] }}" target="_blank" rel="noopener noreferrer">{{ $product['name'] ?? '—' }}</a>
                                        @else
                                            {{ $product['name'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="text-center">{{ isset($product['price']) ? $product['price'] : '—' }}</td>
                                    <td class="text-center">
                                        @php $status = $product['status'] ?? ''; @endphp
                                        @if ($status === 'publish')
                                            <span class="badge bg-success">{{ __('Published') }}</span>
                                        @elseif ($status === 'draft')
                                            <span class="badge bg-secondary">{{ __('Draft') }}</span>
                                        @else
                                            <span class="badge bg-label-secondary">{{ $status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if (isset($product['stock_status']))
                                            {{ $product['stock_status'] === 'instock' ? __('In stock') : __('Out of stock') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-center align-items-center">
                                            @can('update', new \App\Models\Product(['team_id' => auth()->user()->currentTeam?->id]))
                                                <a href="{{ route('product.edit', $product['id']) }}" class="text-body" title="{{ __('Edit') }}">
                                                    <i class="ti ti-edit ti-sm me-2"></i>
                                                </a>
                                            @endcan
                                            @if (!empty($product['permalink']))
                                                <a href="{{ $product['permalink'] }}" target="_blank" rel="noopener noreferrer" class="text-body" title="{{ __('View') }}">
                                                    <i class="ti ti-eye ti-sm me-2"></i>
                                                </a>
                                            @endif
                                            <a href="{{ $storeUrl }}/wp-admin/post.php?post={{ $product['id'] }}&action=edit" target="_blank" rel="noopener noreferrer" class="text-body" title="{{ __('View in WooCommerce') }}">
                                                <i class="ti ti-external-link ti-sm"></i>
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
