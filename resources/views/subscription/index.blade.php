@extends('layouts/layoutMaster')

@section('title', 'Subscription Plans')

@section('content')
<!-- Pricing Plans -->
<div class="text-center mb-5">
	<h1 class="mb-2">Pricing Plans</h1>
	<p class="mb-5">Get started with us - it's perfect for individuals and teams. Choose a subscription plan that meets your needs.</p>
</div>

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

<!-- Current Plan Status (if not free) -->
@if($currentPlan !== \App\Enums\EmailPlan::FREE)
<div class="card mb-5">
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<h5 class="mb-3">Current Plan: {{ $currentPlan->getDisplayName() }}</h5>
				<p class="text-muted mb-3">{{ $currentPlan->getDescription() }}</p>
				
				@if($subscription)
					<div class="mb-2">
						<span class="badge bg-label-{{ $subscription->onGracePeriod() ? 'warning' : ($subscription->active() ? 'success' : 'secondary') }}">
							@if($subscription->onGracePeriod())
								Cancels on {{ $subscription->ends_at->format('M d, Y') }}
							@elseif($subscription->active())
								Active
							@else
								Inactive
							@endif
						</span>
					</div>
					
					@if($subscription->active() && !$subscription->onGracePeriod())
						<p class="text-muted mb-0">
							<small><i class="ti ti-calendar ti-xs me-1"></i>Next billing: {{ $subscription->asStripeSubscription()->current_period_end ? \Carbon\Carbon::createFromTimestamp($subscription->asStripeSubscription()->current_period_end)->format('M d, Y') : 'N/A' }}</small>
						</p>
					@endif
				@endif
			</div>
			<div class="col-md-6">
				<h6 class="mb-3">Usage Statistics</h6>
				<div class="mb-2">
					<div class="d-flex justify-content-between mb-1">
						<small>Monthly Emails</small>
						<small class="fw-medium">{{ number_format($planConfig['monthly_used']) }} / {{ number_format($planConfig['monthly_limit']) }}</small>
					</div>
					<div class="progress" style="height: 6px;">
						<div class="progress-bar" role="progressbar" style="width: {{ min(($planConfig['monthly_used'] / $planConfig['monthly_limit']) * 100, 100) }}%"></div>
					</div>
				</div>
				
				@if($planConfig['daily_limit'])
				<div class="mb-2">
					<div class="d-flex justify-content-between mb-1">
						<small>Daily Emails</small>
						<small class="fw-medium">{{ number_format($planConfig['daily_used']) }} / {{ number_format($planConfig['daily_limit']) }}</small>
					</div>
					<div class="progress" style="height: 6px;">
						<div class="progress-bar" role="progressbar" style="width: {{ min(($planConfig['daily_used'] / $planConfig['daily_limit']) * 100, 100) }}%"></div>
					</div>
				</div>
				@endif
				
				<div class="mb-0">
					<div class="d-flex justify-content-between mb-1">
						<small>Contacts</small>
						<small class="fw-medium">{{ number_format($team->contacts()->count()) }} / {{ number_format($planConfig['contact_limit']) }}</small>
					</div>
					<div class="progress" style="height: 6px;">
						<div class="progress-bar bg-info" role="progressbar" style="width: {{ min(($team->contacts()->count() / $planConfig['contact_limit']) * 100, 100) }}%"></div>
					</div>
				</div>
			</div>
		</div>
		
		@if($subscription)
		<div class="mt-4 pt-3 border-top">
			@if($subscription->onGracePeriod())
				<form method="POST" action="{{ route('subscription.resume') }}" class="d-inline">
					@csrf
					<button type="submit" class="btn btn-success btn-sm">
						<i class="ti ti-refresh ti-xs me-1"></i>Resume Subscription
					</button>
				</form>
			@elseif($subscription->active())
				<button type="button" class="btn btn-label-danger btn-sm" onclick="confirmCancel()">
					<i class="ti ti-x ti-xs me-1"></i>Cancel Subscription
				</button>
			@endif
		</div>
		@endif
	</div>
</div>
@endif

<!-- Pricing Cards -->
<div class="row gy-4">
	<!-- FREE Plan -->
	<div class="col-xl col-lg-4 col-md-6">
		<div class="card border h-100 {{ $currentPlan === \App\Enums\EmailPlan::FREE ? 'border-primary shadow-sm' : '' }}">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::FREE)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif
				
				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">0</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
				</div>

				<h4>Free</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::FREE->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>{{ number_format(\App\Enums\EmailPlan::FREE->getMonthlyLimit()) }} emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>{{ number_format(\App\Enums\EmailPlan::FREE->getDailyLimit()) }} emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta {{ number_format(\App\Enums\EmailPlan::FREE->getContactLimit()) }} contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::FREE)
					<button class="btn btn-label-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@else
					<button class="btn btn-label-secondary w-100 mt-auto" disabled>Downgrade</button>
				@endif
			</div>
		</div>
	</div>

	<!-- BASIC Plan -->
	<div class="col-xl col-lg-4 col-md-6">
		<div class="card border h-100 {{ $currentPlan === \App\Enums\EmailPlan::BASIC ? 'border-primary shadow-sm' : '' }}">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::BASIC)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif
				
				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">49</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
				</div>

				<h4>Basic</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::BASIC->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::BASIC->getMonthlyLimit()) }}</strong> emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::BASIC->getDailyLimit()) }}</strong> emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta <strong>{{ number_format(\App\Enums\EmailPlan::BASIC->getContactLimit()) }}</strong> contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::BASIC)
					<button class="btn btn-label-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@elseif(!$subscription || !$subscription->active())
					<form method="POST" action="{{ route('subscription.checkout') }}" class="mt-auto w-100">
						@csrf
						<input type="hidden" name="plan" value="basic">
						<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
					</form>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('basic', 'Basic')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::BASIC->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>

	<!-- FOUNDATION Plan -->
	<div class="col-xl col-lg-4 col-md-6">
		<div class="card border border-primary shadow-sm h-100">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::FOUNDATION)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@else
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Popular</span>
					</div>
				@endif
				
				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">99</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
				</div>

				<h4>Foundation</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::FOUNDATION->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::FOUNDATION->getMonthlyLimit()) }}</strong> emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::FOUNDATION->getDailyLimit()) }}</strong> emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta <strong>{{ number_format(\App\Enums\EmailPlan::FOUNDATION->getContactLimit()) }}</strong> contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::FOUNDATION)
					<button class="btn btn-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@elseif(!$subscription || !$subscription->active())
					<form method="POST" action="{{ route('subscription.checkout') }}" class="mt-auto w-100">
						@csrf
						<input type="hidden" name="plan" value="foundation">
						<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
					</form>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('foundation', 'Foundation')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::FOUNDATION->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>

	<!-- SCALE Plan -->
	<div class="col-xl col-lg-4 col-md-6">
		<div class="card border h-100 {{ $currentPlan === \App\Enums\EmailPlan::SCALE ? 'border-primary shadow-sm' : '' }}">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::SCALE)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif
				
				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">199</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
				</div>

				<h4>Scale</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::SCALE->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::SCALE->getMonthlyLimit()) }}</strong> emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>Ilimitados</strong> emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta <strong>{{ number_format(\App\Enums\EmailPlan::SCALE->getContactLimit()) }}</strong> contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::SCALE)
					<button class="btn btn-label-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@elseif(!$subscription || !$subscription->active())
					<form method="POST" action="{{ route('subscription.checkout') }}" class="mt-auto w-100">
						@csrf
						<input type="hidden" name="plan" value="scale">
						<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
					</form>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('scale', 'Scale')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::SCALE->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>
</div>

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

<script>
function confirmCancel()
{
	Swal.fire({
		title: 'Cancel Subscription?',
		text: "Your subscription will remain active until the end of your billing period.",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#d33',
		cancelButtonColor: '#3085d6',
		confirmButtonText: 'Yes, cancel it',
		cancelButtonText: 'No, keep it',
		customClass: {
			confirmButton: 'btn btn-danger me-2',
			cancelButton: 'btn btn-label-secondary'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed)
		{
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = '{{ route("subscription.cancel") }}';
			form.innerHTML = '@csrf';
			document.body.appendChild(form);
			form.submit();
		}
	});
}

function confirmSwap(plan, planName)
{
	Swal.fire({
		title: 'Change Plan?',
		text: `Switch to ${planName} plan? Changes will take effect immediately.`,
		icon: 'question',
		showCancelButton: true,
		confirmButtonColor: '#696cff',
		cancelButtonColor: '#8592a3',
		confirmButtonText: 'Yes, switch plan',
		cancelButtonText: 'Cancel',
		customClass: {
			confirmButton: 'btn btn-primary me-2',
			cancelButton: 'btn btn-label-secondary'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed)
		{
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = '{{ route("subscription.swap") }}';
			form.innerHTML = '@csrf<input type="hidden" name="plan" value="' + plan + '">';
			document.body.appendChild(form);
			form.submit();
		}
	});
}
</script>
@endsection
