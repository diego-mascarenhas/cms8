@extends('layouts/layoutMaster')

@section('title', 'Planes de Suscripción')

@section('content')
<!-- Pricing Plans -->
<div class="text-center mb-5">
	<h1 class="mb-2">Planes de Suscripción</h1>
	<p class="mb-5">Comienza con nosotros - es perfecto para individuos y equipos. Elige un plan que se ajuste a tus necesidades.</p>
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
					<small class="text-muted">&nbsp;</small>
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
						<h1 class="mb-0 text-primary">{{ $prices['basic'] ? number_format($prices['basic']['amount'], 2, ',', '.') : '15,99' }}</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">+ I.V.A.</small>
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
						<h1 class="mb-0 text-primary">{{ $prices['foundation'] ? number_format($prices['foundation']['amount'], 2, ',', '.') : '35,99' }}</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">+ I.V.A.</small>
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
						<h1 class="mb-0 text-primary">{{ $prices['scale'] ? number_format($prices['scale']['amount'], 2, ',', '.') : '119,99' }}</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">+ I.V.A.</small>
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
