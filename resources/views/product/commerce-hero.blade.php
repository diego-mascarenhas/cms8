@php
	$commerceMode = $commerceMode ?? 'setup';
	$storeUrl = $storeUrl ?? null;
@endphp

{{-- WooCommerce-inspired commerce hero (Vuexy / Bootstrap utilities only) --}}
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
	<div class="card-body p-4 p-md-5 bg-primary text-white">
		<div class="row align-items-center gy-3">
			<div class="col-lg-8">
				<h3 class="fw-bold text-white mb-2">
					@if ($commerceMode === 'woo')
						{{ __('WooCommerce storefront') }}
					@else
						{{ __('Your commerce command center') }}
					@endif
				</h3>
				<p class="mb-0 text-white-50">
					@if ($commerceMode === 'woo')
						{{ __('Manage products from your connected store. Multi-store sync for client shops is planned so every catalogue stays aligned from one workspace—similar to flexible commerce on WooCommerce.') }}
					@else
						{{ __('Connect WooCommerce to mirror your catalogue, prepare for syncing products from client stores, and feed assistants with live data. Flexible commerce, centralized here—like WooCommerce for WordPress, built for your teams.') }}
					@endif
				</p>
			</div>
			<div class="col-lg-4 text-lg-end">
				<div class="d-flex flex-column flex-sm-row flex-lg-column align-items-stretch align-items-lg-end gap-2">
					@if ($commerceMode === 'woo')
						@can('create', \App\Models\Product::class)
							<a href="{{ route('product.create') }}" class="btn btn-light btn-lg">
								<i class="ti ti-plus me-1"></i>{{ __('Add product') }}
							</a>
						@endcan
						@if (! empty($storeUrl))
							<a href="{{ $storeUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light">
								<i class="ti ti-external-link me-1"></i>{{ __('Open store') }}
							</a>
						@endif
					@else
						@can('update', auth()->user()->currentTeam)
							<a href="{{ route('team-settings.edit', ['team' => auth()->user()->currentTeam, 'group' => 'woocommerce']) }}" class="btn btn-light btn-lg">
								<i class="ti ti-link me-1"></i>{{ __('Connect WooCommerce store') }}
							</a>
						@endcan
						<a href="{{ route('help.woocommerce-configuration') }}" class="btn btn-outline-light">
							<i class="ti ti-book me-1"></i>{{ __('View setup guide') }}
						</a>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>
