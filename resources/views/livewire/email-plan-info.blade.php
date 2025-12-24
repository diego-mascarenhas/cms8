<div class="card mb-4" wire:poll.15s>
	<div class="card-header d-flex justify-content-between align-items-center">
		<h5 class="card-title mb-0">{{ __('Email Plan') }}</h5>
		<span class="badge bg-label-{{ $currentPlan->value === 'basic' ? 'primary' : ($currentPlan->value === 'foundation' ? 'info' : 'success') }}">
			{{ $currentPlan->getDisplayName() }}
		</span>
	</div>
	<div class="card-body">
		<!-- Monthly Usage -->
		<div class="mb-3">
			<div class="d-flex justify-content-between mb-2">
				<span class="text-muted">{{ __('Monthly Usage') }}</span>
				<span class="fw-semibold">
					{{ number_format($remaining['monthly_used'], 0, ',', '.') }} / {{ number_format($remaining['monthly_limit'], 0, ',', '.') }}
				</span>
			</div>
			<div class="progress" style="height: 8px;">
				<div class="progress-bar bg-{{ $monthlyColor }}" role="progressbar"
					 style="width: {{ min(100, $monthlyPercent) }}%"
					 aria-valuenow="{{ $monthlyPercent }}" aria-valuemin="0" aria-valuemax="100">
				</div>
			</div>
		</div>

		<!-- Daily Usage -->
		<div class="mb-3">
			<div class="d-flex justify-content-between mb-2">
				<span class="text-muted">{{ __('Daily Usage') }}</span>
				<span class="fw-semibold">
					{{ number_format($remaining['daily_used'], 0, ',', '.') }} /
					{{ $remaining['daily_limit'] ? number_format($remaining['daily_limit'], 0, ',', '.') : '∞' }}
				</span>
			</div>
			<div class="progress" style="height: 8px;">
				@if($remaining['daily_limit'])
					<div class="progress-bar bg-{{ $dailyColor }}" role="progressbar"
						 style="width: {{ min(100, $dailyPercent) }}%"
						 aria-valuenow="{{ $dailyPercent }}" aria-valuemin="0" aria-valuemax="100">
					</div>
				@else
					<div class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
				@endif
			</div>
		</div>

		<!-- Contacts -->
		<div class="mb-3">
			<div class="d-flex justify-content-between mb-2">
				<span class="text-muted">{{ __('Contacts') }}</span>
				<span class="fw-semibold">
					{{ number_format($contactsCount, 0, ',', '.') }} / {{ number_format($contactLimit, 0, ',', '.') }}
				</span>
			</div>
			<div class="progress" style="height: 8px;">
				<div class="progress-bar bg-{{ $contactsColor }}" role="progressbar"
					 style="width: {{ min(100, $contactsPercent) }}%"
					 aria-valuenow="{{ $contactsPercent }}" aria-valuemin="0" aria-valuemax="100">
				</div>
			</div>
		</div>

		<!-- Upgrade Button - Temporarily hidden -->
		{{--
		<a href="{{ route('email-plans.current') }}" class="btn btn-primary w-100">
			<i class="ti ti-rocket me-1"></i>
			{{ __('Upgrade Plan') }}
		</a>
		--}}
	</div>
</div>
