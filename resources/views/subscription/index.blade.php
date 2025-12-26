@extends('layouts/layoutMaster')

@section('title', 'Subscription Management')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">Subscription Management</h4>
		<p class="text-muted">Manage your email sending plan and billing</p>
	</div>
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

<div class="row">
	<!-- Current Plan Card -->
	<div class="col-12 mb-4">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="mb-0">Current Plan</h5>
				@if($subscription && $subscription->onGracePeriod())
					<span class="badge bg-warning">Cancelling</span>
				@elseif($subscription && $subscription->active())
					<span class="badge bg-success">Active</span>
				@else
					<span class="badge bg-secondary">Inactive</span>
				@endif
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-6">
						<h4>{{ $currentPlan->getDisplayName() }} Plan</h4>
						<p class="text-muted">{{ $currentPlan->getDescription() }}</p>

						<div class="mt-3">
							<h6>Plan Limits:</h6>
							<ul class="list-unstyled">
								<li><i class="ti ti-check text-success me-2"></i>{{ number_format($planConfig['monthly_limit']) }} emails per month</li>
								<li><i class="ti ti-check text-success me-2"></i>
									@if($planConfig['daily_limit'])
										{{ number_format($planConfig['daily_limit']) }} emails per day
									@else
										Unlimited daily emails
									@endif
								</li>
								<li><i class="ti ti-check text-success me-2"></i>Up to {{ number_format($planConfig['contact_limit']) }} contacts</li>
							</ul>
						</div>

						@if($subscription)
							<div class="mt-3">
								<h6>Subscription Details:</h6>
								<p class="mb-1"><strong>Status:</strong>
									@if($subscription->onGracePeriod())
										<span class="badge bg-warning">Cancels on {{ $subscription->ends_at->format('M d, Y') }}</span>
									@elseif($subscription->active())
										<span class="badge bg-success">Active</span>
									@else
										<span class="badge bg-secondary">Inactive</span>
									@endif
								</p>
								@if($subscription->active())
									<p class="mb-1"><strong>Next billing:</strong> {{ $subscription->asStripeSubscription()->current_period_end ? \Carbon\Carbon::createFromTimestamp($subscription->asStripeSubscription()->current_period_end)->format('M d, Y') : 'N/A' }}</p>
								@endif
							</div>
						@endif
					</div>

					<div class="col-md-6">
						<h6>Current Usage:</h6>
						<div class="mb-3">
							<div class="d-flex justify-content-between mb-1">
								<span>Monthly Emails</span>
								<span>{{ number_format($planConfig['monthly_used']) }} / {{ number_format($planConfig['monthly_limit']) }}</span>
							</div>
							<div class="progress">
								<div class="progress-bar {{ $planConfig['monthly_remaining'] < ($planConfig['monthly_limit'] * 0.2) ? 'bg-danger' : ($planConfig['monthly_remaining'] < ($planConfig['monthly_limit'] * 0.5) ? 'bg-warning' : 'bg-success') }}"
									 role="progressbar"
									 style="width: {{ ($planConfig['monthly_used'] / $planConfig['monthly_limit']) * 100 }}%">
								</div>
							</div>
						</div>

						@if($planConfig['daily_limit'])
							<div class="mb-3">
								<div class="d-flex justify-content-between mb-1">
									<span>Daily Emails</span>
									<span>{{ number_format($planConfig['daily_used']) }} / {{ number_format($planConfig['daily_limit']) }}</span>
								</div>
								<div class="progress">
									<div class="progress-bar {{ $planConfig['daily_remaining'] < ($planConfig['daily_limit'] * 0.2) ? 'bg-danger' : ($planConfig['daily_remaining'] < ($planConfig['daily_limit'] * 0.5) ? 'bg-warning' : 'bg-success') }}"
										 role="progressbar"
										 style="width: {{ ($planConfig['daily_used'] / $planConfig['daily_limit']) * 100 }}%">
									</div>
								</div>
							</div>
						@endif

						<div class="mb-3">
							<div class="d-flex justify-content-between mb-1">
								<span>Contacts</span>
								<span>{{ number_format($team->contacts()->count()) }} / {{ number_format($planConfig['contact_limit']) }}</span>
							</div>
							<div class="progress">
								<div class="progress-bar bg-info"
									 role="progressbar"
									 style="width: {{ ($team->contacts()->count() / $planConfig['contact_limit']) * 100 }}%">
								</div>
							</div>
						</div>

						@if($subscription && $subscription->onGracePeriod())
							<form method="POST" action="{{ route('subscription.resume') }}" class="mt-3">
								@csrf
								<button type="submit" class="btn btn-success w-100">
									<i class="ti ti-refresh me-1"></i>Resume Subscription
								</button>
							</form>
						@elseif($subscription && $subscription->active())
							<button type="button" class="btn btn-outline-danger w-100 mt-3" onclick="confirmCancel()">
								<i class="ti ti-x me-1"></i>Cancel Subscription
							</button>
						@endif
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Available Plans -->
	<div class="col-12">
		<h5 class="mb-3">Available Plans</h5>
	</div>

	@foreach($plans as $plan)
		@if($plan !== \App\Enums\EmailPlan::FREE)
			<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
				<div class="card {{ $currentPlan === $plan ? 'border border-primary' : '' }}">
					<div class="card-header text-center {{ $currentPlan === $plan ? 'bg-label-primary' : '' }}">
						<h4 class="mb-0">{{ $plan->getDisplayName() }}</h4>
						@if($currentPlan === $plan)
							<span class="badge bg-primary mt-2">Current Plan</span>
						@endif
					</div>
					<div class="card-body">
						<p class="text-center text-muted mb-4">{{ $plan->getDescription() }}</p>

						<ul class="list-unstyled mb-4">
							<li class="mb-2">
								<i class="ti ti-check text-success me-2"></i>
								<strong>{{ number_format($plan->getMonthlyLimit()) }}</strong> emails/month
							</li>
							<li class="mb-2">
								<i class="ti ti-check text-success me-2"></i>
								@if($plan->getDailyLimit())
									<strong>{{ number_format($plan->getDailyLimit()) }}</strong> emails/day
								@else
									<strong>Unlimited</strong> daily emails
								@endif
							</li>
							<li class="mb-2">
								<i class="ti ti-check text-success me-2"></i>
								Up to <strong>{{ number_format($plan->getContactLimit()) }}</strong> contacts
							</li>
						</ul>

						<div class="text-center">
							@if($currentPlan === $plan)
								<button class="btn btn-label-primary w-100" disabled>
									Current Plan
								</button>
							@elseif(!$subscription || !$subscription->active())
								<form method="POST" action="{{ route('subscription.checkout') }}">
									@csrf
									<input type="hidden" name="plan" value="{{ $plan->value }}">
									<button type="submit" class="btn btn-primary w-100">
										<i class="ti ti-credit-card me-1"></i>Subscribe Now
									</button>
								</form>
							@else
								<button type="button" class="btn btn-primary w-100" onclick="confirmSwap('{{ $plan->value }}', '{{ $plan->getDisplayName() }}')">
									<i class="ti ti-arrow-up me-1"></i>
									@if($currentPlan->getMonthlyLimit() < $plan->getMonthlyLimit())
										Upgrade
									@else
										Downgrade
									@endif
								</button>
							@endif
						</div>
					</div>
				</div>
			</div>
		@endif
	@endforeach
</div>

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
		cancelButtonText: 'No, keep it'
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
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#6c757d',
		confirmButtonText: 'Yes, switch plan',
		cancelButtonText: 'Cancel'
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

