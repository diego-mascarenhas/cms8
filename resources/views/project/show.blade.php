@extends('layouts/layoutMaster')

@section('title', $project->name)

@section('vendor-style')
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('page-style')
	<style>
		.timeline-center::before {
			left: 50%;
			transform: translateX(-50%);
		}
		.timeline-center .timeline-item:nth-child(odd) .timeline-event {
			text-align: right;
		}
		.timeline-center .timeline-item:nth-child(odd) .timeline-point {
			left: 50%;
			transform: translateX(-50%);
		}
		.timeline-center .timeline-item:nth-child(even) .timeline-point {
			left: 50%;
			transform: translateX(-50%);
		}
		.project-stats .card {
			border: none;
			box-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
			transition: transform 0.2s;
		}
		.project-stats .card:hover {
			transform: translateY(-2px);
		}
		.timeline-card {
			max-height: 600px;
			overflow-y: auto;
		}
	</style>
@endsection

@section('vendor-script')
	<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
	<!-- Header -->
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Projects') }}/</span> {{ $project->real_name ?? $project->name }}</h4>
			<p class="text-muted">{{ __('Created on') }} {{ \Carbon\Carbon::parse($project->created_at)->format('F d, Y') }}</p>
		</div>
		<div class="d-flex align-content-center flex-wrap gap-3">
			@can('update', $project)
				@if ($project->isBudgetContentLocked())
					<button type="button" class="btn btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#projectStatusModal">
						<i class="ti ti-exchange me-1"></i>{{ __('Change status') }}
					</button>
				@else
					<a href="{{ route('project.edit', $project->id) }}" class="btn btn-primary waves-effect waves-light">
						<i class="ti ti-edit me-1"></i>{{ __('Edit') }}
					</a>
				@endif
			@endcan
			@if ($project->enterprise_id)
			<a href="{{ route('client.show', $project->enterprise_id) }}" class="btn btn-outline-primary waves-effect waves-light">
				<i class="ti ti-building me-1"></i>{{ __('Enterprise') }}
			</a>
			@endif
			{{-- Collaborators temporarily hidden
			@can('update', $project)
			<a href="{{ route('project.select-collaborators', $project->id) }}" class="btn btn-success waves-effect waves-light">
				<i class="ti ti-users me-1"></i>{{ __('Collaborators') }}
			</a>
			@endcan
			--}}
			<a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $project->id]) }}" class="btn btn-info waves-effect waves-light">
				<i class="ti ti-layout-kanban me-1"></i>{{ __('Board') }}
			</a>
			@if(data_get($project->data, 'budget_preview_token') && auth()->user()->can('manageBudget', $project))
				<a href="{{ route('project.budget-preview', data_get($project->data, 'budget_preview_token')) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary waves-effect waves-light">
					<i class="ti ti-file-invoice me-1"></i>{{ __('Preview') }}
				</a>
			@endif
			@php
				$quoteRecipient = $project->quoteRecipientSummary();
				$canAuthorizeBudget = auth()->user()->hasRole('admin')
					&& (int) $project->status_id === \App\Models\ProjectStatus::STATUS_BUDGETED
					&& data_get($project->data, 'budget_preview_token')
					&& data_get($project->data, 'budget_client_response.status') !== 'accepted'
					&& $quoteRecipient !== null;
				$authorizeConfirm = $quoteRecipient
					? __('Mark as authorized and email the quote to :name (:email)?', ['name' => $quoteRecipient['name'], 'email' => $quoteRecipient['email']])
					: __('Mark as authorized and email the quote to the enterprise contact?');
				$resendConfirm = $quoteRecipient
					? __('Resend the quote email to :name (:email)?', ['name' => $quoteRecipient['name'], 'email' => $quoteRecipient['email']])
					: __('Resend the quote email to the enterprise contact?');
			@endphp
			@can('update', $project)
				@if ($canAuthorizeBudget)
					<form action="{{ route('project.authorize-budget', $project->id) }}" method="POST" class="d-inline">
						@csrf
						<button type="submit" class="btn btn-success waves-effect waves-light"
							onclick="return confirm(@json($authorizeConfirm))">
							<i class="ti ti-mail-forward me-1"></i>{{ __('Authorize quote') }}
						</button>
					</form>
				@elseif (auth()->user()->hasRole('admin')
					&& (int) $project->status_id === \App\Models\ProjectStatus::STATUS_AUTHORIZED
					&& data_get($project->data, 'budget_preview_token')
					&& data_get($project->data, 'budget_client_response.status') !== 'accepted'
					&& $quoteRecipient !== null)
					<form action="{{ route('project.authorize-budget', $project->id) }}" method="POST" class="d-inline">
						@csrf
						<button type="submit" class="btn btn-outline-success waves-effect waves-light"
							onclick="return confirm(@json($resendConfirm))">
							<i class="ti ti-mail-forward me-1"></i>{{ __('Resend quote email') }}
						</button>
					</form>
				@endif
			@endcan
			@role('admin|collaborator|developer|editor|technical')
				<a href="{{ route('project-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}</a>
			@endrole
		</div>
	</div>

@if (session('success'))
	<div class="alert alert-success alert-dismissible" role="alert">
		{{ session('success') }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
@endif
@if (session('error'))
	<div class="alert alert-danger alert-dismissible" role="alert">
		{{ session('error') }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
@endif

@if (! empty($depositInvoicePreview))
	@php
		$formatDepositMoney = fn ($amount) => number_format((float) $amount, 2, ',', '.').' €';
		$depositAlreadyInvoiced = (bool) ($depositInvoicePreview['already_invoiced'] ?? false);
	@endphp
	<div class="row mb-4">
		<div class="col-12">
			@if ($depositAlreadyInvoiced)
				<div class="alert alert-success mb-0" role="alert">
					<div class="d-flex align-items-center flex-wrap gap-2">
						<i class="ti ti-file-check ti-lg me-2 flex-shrink-0"></i>
						<div class="flex-grow-1 min-w-0">
							<span class="fw-medium d-block">{{ __('Deposit invoice already issued') }}</span>
							<span class="small text-muted d-block mt-1">
								{{ __('The 30% advance for this approved budget was invoiced.') }}
								@if (! empty($depositInvoicePreview['existing_stripe_invoice_id']))
									· {{ $depositInvoicePreview['existing_stripe_invoice_id'] }}
								@endif
							</span>
						</div>
						@if (! empty($depositInvoicePreview['existing_invoice_id']))
							<a href="{{ route('invoice.show', $depositInvoicePreview['existing_invoice_id']) }}" class="btn btn-success btn-sm waves-effect waves-light">
								<i class="ti ti-file-invoice ti-sm me-1"></i>{{ __('View invoice') }}
							</a>
						@endif
					</div>
				</div>
			@else
				<div class="alert alert-warning mb-0" role="alert">
					<form method="POST" action="{{ route('project.invoice-deposit', $project->id) }}">
						@csrf
						<div class="d-flex align-items-start flex-wrap gap-2 mb-2">
							<i class="ti ti-file-invoice ti-lg me-2 flex-shrink-0 mt-1"></i>
							<div class="flex-grow-1 min-w-0">
								<span class="fw-medium d-block">{{ __('Approved budget — invoice the 30% deposit') }}</span>
								<span class="small text-muted d-block mt-1">
									{{ __('Deposit') }}: {{ $formatDepositMoney($depositInvoicePreview['deposit_base']) }}
									· {{ $depositInvoicePreview['vat_label'] }}
									@if ($depositInvoicePreview['vat_applies'])
										({{ $formatDepositMoney($depositInvoicePreview['vat_amount']) }})
									@endif
									· {{ __('Total') }}: {{ $formatDepositMoney($depositInvoicePreview['total_with_vat']) }}
									@if (! empty($depositInvoicePreview['stripe_customer_id']))
										· <code>{{ $depositInvoicePreview['stripe_customer_id'] }}</code>
									@endif
								</span>
								@if (empty($depositInvoicePreview['stripe_customer_id']))
									<span class="small text-danger d-block mt-1">
										{{ __('Link a Stripe customer on the client before invoicing the deposit.') }}
										@if ($project->enterprise_id)
											<a href="{{ route('client.show', $project->enterprise_id) }}" class="alert-link">{{ __('Open client') }}</a>
										@endif
									</span>
								@endif
							</div>
							@can('update', $project)
								<button type="submit" class="btn btn-warning btn-sm waves-effect waves-light flex-shrink-0"
									{{ empty($depositInvoicePreview['stripe_customer_id']) ? 'disabled' : '' }}>
									<i class="ti ti-cash ti-sm me-1"></i>{{ __('Invoice deposit') }}
								</button>
							@endcan
						</div>

						@can('update', $project)
							<label for="deposit-invoice-description" class="form-label mb-1">{{ __('Invoice description') }}</label>
							<textarea
								id="deposit-invoice-description"
								name="description"
								class="form-control bg-white @error('description') is-invalid @enderror"
								rows="2"
								required
								maxlength="500"
								placeholder="{{ __('Invoice description') }}"
							>{{ old('description', $depositInvoicePreview['default_description']) }}</textarea>
							@error('description')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
						@endcan
					</form>
				</div>
			@endif
		</div>
	</div>
@endif

@if (session('deposit_invoice_url'))
	<div class="row mb-4">
		<div class="col-12">
			<div class="alert alert-info mb-0" role="alert">
				<div class="d-flex align-items-center flex-wrap gap-2">
					<i class="ti ti-link ti-lg me-2 flex-shrink-0"></i>
					<div class="flex-grow-1 min-w-0">
						<span class="fw-medium d-block">{{ __('Stripe invoice ready') }}</span>
						<span class="small text-muted">{{ __('Open the hosted Stripe invoice to send or collect payment.') }}</span>
					</div>
					<a href="{{ session('deposit_invoice_url') }}" target="_blank" rel="noopener noreferrer" class="btn btn-info btn-sm waves-effect waves-light">
						<i class="ti ti-external-link ti-sm me-1"></i>{{ __('Open in Stripe') }}
					</a>
				</div>
			</div>
		</div>
	</div>
@endif

<!-- Project Details Card - Full Width -->
<div class="card mb-4">
   <div class="card-header">
       <h5 class="mb-0">{{ __('Project Details') }}</h5>
   </div>
   <div class="card-body">
       <div class="row">
           <div class="col-md-6">
               <dl class="row mb-0">
								<dt class="col-4 text-truncate">{{ __('Internal Name for Collaborators') }}:</dt>
								<dd class="col-8">{{ $project->name ?: __('Not set') }}</dd>

								<dt class="col-4 text-truncate">{{ __('Client') }}:</dt>
								<dd class="col-8">{{ $project->client ? $project->client->name : __('Not assigned') }}</dd>

								<dt class="col-4 text-truncate">{{ __('Category') }}:</dt>
								<dd class="col-8">{{ $project->category ? $project->category->name : __('Not assigned') }}</dd>

								<dt class="col-4 text-truncate">{{ __('Responsible') }}:</dt>
								<dd class="col-8">{{ $project->responsible ? $project->responsible->name : __('Not assigned') }}</dd>

								@php
									$budgetClientResponse = is_array(data_get($project->data, 'budget_client_response'))
										? data_get($project->data, 'budget_client_response')
										: null;
									$budgetResponseStatus = $budgetClientResponse['status'] ?? null;
									$quoteContact = $project->client?->quoteContact();
								@endphp

								@if ($quoteContact)
								<dt class="col-4 text-truncate">{{ __('Contact') }}:</dt>
								<dd class="col-8">
									<a href="{{ route('contact.show', $quoteContact->id) }}">
										{{ trim($quoteContact->name.' '.(string) ($quoteContact->surname ?? '')) }}
									</a>
								</dd>
								@endif
							</dl>
           </div>
           <div class="col-md-6">
               <dl class="row mb-0">
								<dt class="col-4 text-truncate">{{ __('Start Date') }}:</dt>
								<dd class="col-8">{{ $project->date_start ? \Carbon\Carbon::parse($project->date_start)->format('d/m/Y') : __('Not set') }}</dd>

								@if($project->date_material)
								<dt class="col-4 text-truncate">{{ __('Material Date') }}:</dt>
								<dd class="col-8">{{ \Carbon\Carbon::parse($project->date_material)->format('d/m/Y') }}</dd>
								@endif

								<dt class="col-4 text-truncate">{{ __('End Date') }}:</dt>
								<dd class="col-8">{{ $project->date_end ? \Carbon\Carbon::parse($project->date_end)->format('d/m/Y') : __('Not set') }}</dd>

								@if(auth()->user()->hasRole('admin'))
								@php
									$quoteTotals = app(\App\Services\ProjectBudgetSpecService::class)->computeQuoteTotals($project);
									$formatQuoteEuros = fn (int|float $amount): string => number_format((float) $amount, 2, ',', '.').' €';
								@endphp
								@if ($quoteTotals['grand_total'] > 0)
								<dt class="col-4 text-truncate">{{ __('Price') }}:</dt>
								<dd class="col-8">
									@if ($quoteTotals['discount_percent'] > 0)
										<s class="text-muted">{{ $formatQuoteEuros($quoteTotals['grand_total']) }}</s>
										{{ $formatQuoteEuros($quoteTotals['payable_total']) }}
										<span class="text-muted small">+ {{ __('I.V.A.') }}</span>
									@else
										{{ $formatQuoteEuros($quoteTotals['payable_total']) }}
										<span class="text-muted small">+ {{ __('I.V.A.') }}</span>
									@endif
								</dd>
								@endif
								@if($project->discount)
								<dt class="col-4 text-truncate">{{ __('Discount') }}:</dt>
								<dd class="col-8">{{ $project->discount }}%</dd>
								@endif
								@endif

								<dt class="col-4 text-truncate">{{ __('Status') }}:</dt>
								<dd class="col-8">
									{!! $project->status_label !!}
									@if ($budgetResponseStatus === 'accepted' && ! empty($budgetClientResponse['accepted_by_name']))
										<span class="text-muted ms-1">{{ $budgetClientResponse['accepted_by_name'] }}</span>
									@endif
									@if (! empty($budgetClientResponse['responded_at']))
										<br><span class="text-muted small">{{ __('Recorded on') }}: {{ \Carbon\Carbon::parse($budgetClientResponse['responded_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
									@endif
									@if ($budgetResponseStatus === 'reformulation_requested' && ! empty($budgetClientResponse['message']))
										<br><em class="small d-inline-block mt-1">{{ $budgetClientResponse['message'] }}</em>
									@endif
								</dd>
							</dl>
           </div>
       </div>
       @php
           $budgetEmail = is_array(data_get($project->data, 'budget_email')) ? data_get($project->data, 'budget_email') : null;
           $quoteEmail = filled($budgetEmail['to_email'] ?? null)
               ? $budgetEmail['to_email']
               : ($quoteContact?->email);
       @endphp
       @if (filled($quoteEmail) || $budgetEmail)
       <div class="row mt-3">
           <div class="col-12">
               <dl class="row mb-0">
                   <dt class="col-md-2 col-4 text-truncate">Email:</dt>
                   <dd class="col-md-10 col-8">
                       <div class="d-flex flex-wrap align-items-center gap-2">
                           <span>{{ $quoteEmail ?: '—' }}</span>
                           @if ($budgetEmail)
                               @if (! empty($budgetEmail['sent_at']))
                                   <span class="text-muted small">{{ __('Sent on') }}: {{ \Carbon\Carbon::parse($budgetEmail['sent_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                               @endif
                               @if (! empty($budgetEmail['opened_at']))
                                   <span class="badge rounded-pill bg-label-success">{{ __('Email opened') }}</span>
                                   <span class="text-muted small">{{ \Carbon\Carbon::parse($budgetEmail['opened_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                               @else
                                   <span class="badge rounded-pill bg-label-secondary">{{ __('Not opened yet') }}</span>
                               @endif
                               @if (! empty($budgetEmail['clicked_at']))
                                   <span class="badge rounded-pill bg-label-info">{{ __('Link clicked') }}</span>
                                   <span class="text-muted small">{{ \Carbon\Carbon::parse($budgetEmail['clicked_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                                   @if ((int) ($budgetEmail['visited_count'] ?? 0) > 0)
                                       <span class="text-muted small">{{ trans_choice(':count time|:count times', (int) $budgetEmail['visited_count'], ['count' => (int) $budgetEmail['visited_count']]) }}</span>
                                   @endif
                               @endif
                           @endif
                       </div>
                   </dd>
               </dl>
           </div>
       </div>
       @endif
       <div class="row mt-2">
           <div class="col-12">
               <dl class="row mb-0">
                   <dt class="col-auto">{{ __('Project key (API / MCP)') }}:</dt>
                   <dd class="col mb-0">
                       @auth
                       <code class="user-select-all text-break d-inline-block" style="word-break: break-all;" title="{{ __('Copy for .env: list tasks and auto-assign when you pick one via MCP') }}">HUMANO_PROJECT_KEY={{ $project->contextKeyForUser(auth()->user()) }}</code>
                       @else
                       <code class="user-select-all text-break d-inline-block" style="word-break: break-all;" title="{{ __('Copy for .env') }}">HUMANO_PROJECT_KEY={{ $project->project_key }}</code>
                       <p class="text-muted small mb-0 mt-1">{{ __('Log in to get the key that includes your user (list and assign tasks via MCP).') }}</p>
                       @endauth
                   </dd>
               </dl>
           </div>
       </div>
</div>
</div>

<!-- Tasks and times block -->
<div class="card mb-4">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h5 class="mb-0">{{ __('Tasks and times') }}</h5>
		<a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
			<i class="ti ti-layout-kanban me-1"></i>{{ __('Open Kanban board') }}
		</a>
	</div>
	<div class="card-body">
		@if(isset($projectTasks) && $projectTasks->isNotEmpty())
			@php
				$totalEstimated = $projectTasks->sum('estimated_hours');
				$totalActual = $actualHoursByTaskId->sum();
			@endphp
			<p class="text-muted small mb-3">{{ __('Estimated and actual hours per task.') }}</p>
			<div class="d-flex gap-2 mb-3">
				<span class="badge bg-label-primary">{{ __('Estimated') }}: {{ \App\Helpers\Helpers::formatHoursHuman($totalEstimated) }}</span>
				<span class="badge bg-label-info">{{ __('Actual') }}: {{ \App\Helpers\Helpers::formatHoursHuman($totalActual) }}</span>
			</div>
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<thead>
						<tr>
							<th>{{ __('Task') }}</th>
							<th class="text-center">{{ __('Responsible') }}</th>
							<th class="text-center">{{ __('Status') }}</th>
							<th class="text-end">{{ __('Hours') }}</th>
							<th class="text-end">{{ __('Actual') }}</th>
							<th class="text-center" style="width: 7rem;">{{ __('Timer') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($projectTasks as $task)
						@php
							$isRunningThisTask = isset($runningTimer) && $runningTimer && (int) $runningTimer->task_id === (int) $task->id;
						@endphp
						<tr>
							<td>
								<a href="{{ route('task.show', $task->id) }}">{{ $task->title ?? '—' }}</a>
							</td>
							<td class="text-center">{{ $task->responsible?->name ?? '—' }}</td>
							<td class="text-center">
								@if($task->status)
									<span class="badge rounded-pill {{ $task->status->label_class ?? 'bg-label-secondary' }}">{{ $task->status->translated_name ?? $task->status->name }}</span>
								@else
									—
								@endif
							</td>
							<td class="text-end">{{ \App\Helpers\Helpers::formatHoursHuman($task->estimated_hours) }}</td>
							<td class="text-end">{{ \App\Helpers\Helpers::formatHoursHuman($actualHoursByTaskId->get($task->id, 0)) }}</td>
							<td class="text-center">
								@if($isRunningThisTask)
									<button type="button"
										class="btn btn-sm btn-label-danger project-stop-timer"
										data-time-id="{{ $runningTimer->id }}"
										title="{{ __('Stop timer') }}">
										<i class="ti ti-player-stop"></i>
									</button>
								@else
									<button type="button"
										class="btn btn-sm btn-label-primary project-start-timer"
										data-task-id="{{ $task->id }}"
										title="{{ __('Start timer') }}">
										<i class="ti ti-player-play"></i>
									</button>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr class="fw-semibold">
							<td colspan="3" class="text-end">{{ __('Total') }}</td>
							<td class="text-end">{{ \App\Helpers\Helpers::formatHoursHuman($totalEstimated) }}</td>
							<td class="text-end">{{ \App\Helpers\Helpers::formatHoursHuman($totalActual) }}</td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		@else
			<p class="text-muted mb-0">{{ __('No tasks on this project board yet. Add the suggested tasks below (or in the Kanban) to register time per collaborator and task.') }}</p>
		@endif

		@if(isset($suggestedTasks) && count($suggestedTasks) > 0 && auth()->user()->can('manageBudget', $project))
			@php
				$suggestedTotalHours = collect($suggestedTasks)->sum(fn ($t) => ($t['included'] ?? true) && isset($t['estimated_hours']) && $t['estimated_hours'] !== '' && $t['estimated_hours'] !== null ? (float) $t['estimated_hours'] : 0);
			@endphp
			<hr class="my-4">
			<p class="text-muted small mb-3">{{ __('Suggested tasks from the budget. Assign who will do each and add them to the board.') }}</p>
			<div class="d-flex gap-2 mb-3">
				<span class="badge bg-label-primary">{{ __('Total') }}: {{ \App\Helpers\Helpers::formatHoursHuman($suggestedTotalHours) }}</span>
			</div>
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<thead>
						<tr>
							<th>{{ __('Task') }}</th>
							<th class="text-center">{{ __('Task category') }}</th>
							<th class="text-end">{{ __('Hours') }}</th>
							<th class="text-center">{{ __('Level') }}</th>
							<th style="min-width: 220px;">{{ __('Who will do it') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($suggestedTasks as $idx => $t)
						@php
							$suggestedIncluded = $t['included'] ?? true;
							$onBoard = $t['on_board'] ?? false;
							$responsibleName = null;
							if (!empty($t['responsible_id']) && isset($teamUsers) && isset($teamUsers[$t['responsible_id']])) {
								$responsibleName = $teamUsers[$t['responsible_id']];
							}
						@endphp
						<tr class="{{ $suggestedIncluded ? '' : 'table-secondary' }}">
							<td>{{ $t['title'] ?? '—' }}</td>
							<td class="text-center">{{ $t['category_name'] ?? '—' }}</td>
							<td class="text-end">{{ \App\Helpers\Helpers::formatHoursHuman($t['estimated_hours'] ?? null) }}</td>
							<td class="text-center">{{ $t['resource_level'] ?? '—' }}</td>
							<td>
								@if($onBoard)
									<span class="text-muted">{{ $responsibleName ?? '—' }}</span>
									<span class="badge bg-label-success ms-1">{{ __('On board') }}</span>
								@else
								<form action="{{ route('project.add-suggested-task', $project->id) }}" method="POST" class="d-flex align-items-center gap-2">
									@csrf
									<input type="hidden" name="title" value="{{ $t['title'] ?? '' }}">
									<input type="hidden" name="category_name" value="{{ $t['category_name'] ?? '' }}">
									<input type="hidden" name="estimated_hours" value="{{ $t['estimated_hours'] ?? '' }}">
									<div class="flex-grow-1" style="min-width: 180px;">
										<x-team-users-select
											:id="'suggested_responsible_'.$idx"
											name="responsible_id"
											:label="__('Responsible')"
											:selected="$t['responsible_id'] ?? auth()->id()"
											:compact="true"
											:showNull="true"
											:disabled="! $suggestedIncluded"
										/>
									</div>
									<button type="submit" class="btn btn-sm btn-primary" {{ $suggestedIncluded ? '' : 'disabled' }}>
										<i class="ti ti-layout-kanban me-1"></i>{{ __('Add') }}
									</button>
								</form>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr class="fw-semibold">
							<td colspan="2" class="text-end">{{ __('Total') }}</td>
							<td class="text-end">{{ \App\Helpers\Helpers::formatHoursHuman($suggestedTotalHours) }}</td>
							<td colspan="2"></td>
						</tr>
					</tfoot>
				</table>
			</div>
		@endif
	</div>
</div>

<!-- Time Tracking for Project Tasks -->
@if(isset($timeEntries))
<div class="card mb-4">
   <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
       <h5 class="mb-0">{{ __('Recent time entries') }}</h5>
       <div class="d-flex align-items-center gap-2">
           <span class="badge bg-label-info">{{ __('Total') }}: {{ \App\Helpers\Helpers::formatHoursHuman($totalHours) }}</span>
           @if(isset($projectTasks) && $projectTasks->isNotEmpty())
               <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#projectTimeModal">
                   <i class="ti ti-clock-plus me-1"></i>{{ __('Register time') }}
               </button>
           @endif
       </div>
   </div>
   <div class="card-body">
       @if($timeEntries->isEmpty())
           <p class="text-muted mb-0">
               @if(isset($projectTasks) && $projectTasks->isNotEmpty())
                   {{ __('No time entries for this project yet. Start a timer on a task or register time manually.') }}
               @else
                   {{ __('No time entries for this project. Add tasks to the board first, then register time per collaborator and task.') }}
               @endif
           </p>
       @else
       <div class="table-responsive">
           <table class="table table-hover">
               <thead>
                   <tr>
                       <th>{{ __('Collaborator') }}</th>
                       <th>{{ __('Task') }}</th>
                       <th>{{ __('Description') }}</th>
                       <th class="text-center">{{ __('Start') }}</th>
                       <th class="text-center">{{ __('End') }}</th>
                       <th class="text-end">{{ __('Duration') }}</th>
                   </tr>
               </thead>
               <tbody>
                   @foreach($timeEntries as $entry)
                   <tr>
                       <td>{{ $entry->user?->name ?? '—' }}</td>
                       <td>
                           @if($entry->task)
                               <a href="{{ route('task.show', $entry->task->id) }}">{{ $entry->task->title }}</a>
                           @else
                               —
                           @endif
                       </td>
                       <td>{{ $entry->description ?: '—' }}</td>
                       <td class="text-center">{{ optional($entry->start_time)->format('d/m/Y H:i') }}</td>
                       <td class="text-center">{{ optional($entry->end_time)->format('d/m/Y H:i') ?? '—' }}</td>
                       <td class="text-end">
                           @php
                               $seconds = $entry->duration_seconds ?? ($entry->end_time && $entry->start_time ? $entry->end_time->diffInSeconds($entry->start_time) : 0);
                               $hours = floor($seconds / 3600);
                               $minutes = floor(($seconds % 3600) / 60);
                           @endphp
                           {{ sprintf('%02dh %02dm', $hours, $minutes) }}
                       </td>
                   </tr>
                   @endforeach
               </tbody>
           </table>
       </div>
       @endif
   </div>
</div>
@endif

@if(isset($projectTasks) && $projectTasks->isNotEmpty())
<div class="modal fade" id="projectTimeModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ __('Register time') }}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="POST" action="{{ route('project.time.store', $project->id) }}">
				@csrf
				<div class="modal-body">
					<p class="text-muted small mb-3">{{ __('Log work against a board task. Admins can assign the entry to another team collaborator.') }}</p>
					<div class="mb-3">
						<label for="project-time-task-id" class="form-label">{{ __('Task') }} (*)</label>
						<select id="project-time-task-id" name="task_id" class="select2 form-select" required data-placeholder="{{ __('Choose an option') }}">
							<option value=""></option>
							@foreach($projectTasks as $task)
								<option value="{{ $task->id }}" @selected((int) old('task_id') === (int) $task->id)>
									{{ $task->title }}
								</option>
							@endforeach
						</select>
					</div>
					@if(auth()->user()->hasRole('admin'))
					<div class="mb-3">
						<label for="project-time-user-id" class="form-label">{{ __('Collaborator') }}</label>
						<select id="project-time-user-id" name="user_id" class="select2 form-select" data-placeholder="{{ __('Choose an option') }}">
							@foreach(($teamUsers ?? collect()) as $teamUserId => $teamUserName)
								<option value="{{ $teamUserId }}" @selected((int) old('user_id', auth()->id()) === (int) $teamUserId)>
									{{ $teamUserName }}
								</option>
							@endforeach
						</select>
					</div>
					@endif
					<div class="row g-3">
						<div class="col-md-6">
							<label for="project-time-start" class="form-label">{{ __('Start') }} (*)</label>
							<input type="datetime-local" id="project-time-start" name="start_time" class="form-control"
								value="{{ old('start_time', now()->format('Y-m-d\TH:i')) }}" required>
						</div>
						<div class="col-md-6">
							<label for="project-time-end" class="form-label">{{ __('End') }}</label>
							<input type="datetime-local" id="project-time-end" name="end_time" class="form-control"
								value="{{ old('end_time') }}">
						</div>
						<div class="col-md-6">
							<label for="project-time-duration" class="form-label">{{ __('Duration (hours)') }}</label>
							<input type="number" step="0.25" min="0.25" max="24" id="project-time-duration" name="duration_hours"
								class="form-control" value="{{ old('duration_hours', '1') }}" placeholder="1">
							<small class="text-muted">{{ __('Used when end time is empty.') }}</small>
						</div>
						<div class="col-12">
							<label for="project-time-description" class="form-label">{{ __('Description') }}</label>
							<input type="text" id="project-time-description" name="description" class="form-control"
								value="{{ old('description') }}" maxlength="255">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
					<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif

<!-- Collaborators Section (Floating Cards) -->
@if($project->allCollaborators && $project->allCollaborators->count() > 0)
<div class="row mb-4" style="align-items: stretch;">
    @foreach($project->allCollaborators as $index => $collaborator)
        @php
            // Get the valoration for display
            $valorationIcon = 'ti-star-filled text-warning';
            $valorationText = 'Top';
            if ($collaborator->valoration) {
                switch($collaborator->valoration->name) {
                    case 'Top':
                        $valorationIcon = 'ti-star-filled text-warning';
                        $valorationText = 'Top';
                        break;
                    case 'Validada':
                        $valorationIcon = 'ti-check text-success';
                        $valorationText = 'Validada';
                        break;
                    case 'Interesante':
                        $valorationIcon = 'ti-clock text-info';
                        $valorationText = 'Interesante';
                        break;
                    case 'Ojo':
                        $valorationIcon = 'ti-eye text-warning';
                        $valorationText = 'Ojo';
                        break;
                    case 'Lista negra':
                        $valorationIcon = 'ti-x text-danger';
                        $valorationText = 'Lista negra';
                        break;
                }
            } else {
                // No valoration assigned
                $valorationIcon = 'ti-minus text-muted';
                $valorationText = 'Sin valoración';
            }

            // Get primary language combination for display
            $primaryLanguage = '';
            if ($collaborator->languageVariants->count() > 0) {
                $firstVariant = $collaborator->languageVariants->first();
                $sourceLang = $firstVariant->sourceLanguage ? $firstVariant->sourceLanguage->name : $firstVariant->source_language_code;
                $targetLang = $firstVariant->targetLanguage ? $firstVariant->targetLanguage->name : $firstVariant->target_language_code;
                $primaryLanguage = $sourceLang . ' → ' . $targetLang;
            }

            // Get primary service for display
            $primaryService = '';
            if ($collaborator->fares->count() > 0) {
                $firstFare = $collaborator->fares->first();
                $primaryService = $firstFare->name ?? 'N/A';
                if ($firstFare->type) {
                    $primaryService .= ' (' . $firstFare->type->name . ')';
                }
            }

            // Check if collaborator was removed from project
            $isRemoved = $collaborator->pivot->deleted_at !== null;

            // Get message status
            $messageStatus = $collaborator->pivot->status ?? 'sent';
            $messageStatusClass = [
                'sent' => 'bg-label-info',
                'viewed' => 'bg-label-warning',
                'accepted' => 'bg-label-success',
                'rejected' => 'bg-label-danger'
            ][$messageStatus] ?? 'bg-label-secondary';
        @endphp

        <div class="col-md-6 mb-3 d-flex">
            <div class="card border w-100 {{ $isRemoved ? 'bg-light opacity-75' : '' }}">
                <div class="card-body p-3 position-relative">
                    <!-- Dropdown Menu in top right corner -->
                    <div class="position-absolute top-0 end-0 mt-2 me-2">
                        <div class="dropdown">
                            <button class="btn p-0" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{ route('collaborator.show', $collaborator->id) }}">
                                    <i class="ti ti-eye me-2"></i>{{ __('View Details') }}
                                </a>
                                @if(!$isRemoved)
                                <a class="dropdown-item text-danger" href="javascript:void(0)"
                                   onclick="removeCollaboratorFromProject({{ $project->id }}, {{ $collaborator->id }}, '{{ $collaborator->name }}')">
                                    <i class="ti ti-trash me-2"></i>{{ __('Remove from Project') }}
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Collaborator Info -->
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3">
                            <span class="avatar-initial rounded-circle bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][($index % 5)] }}">{{ strtoupper(substr($collaborator->name, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $collaborator->name }}</h6>
                            @if($primaryService)
                                <small class="text-muted">{{ $primaryService }}</small>
                            @endif
                            <small class="text-muted d-block">{{ $primaryLanguage }}</small>
                            <div class="d-flex align-items-center mt-1">
                                <i class="ti {{ $valorationIcon }} ti-xs me-1"></i>
                                <small class="text-muted">{{ $valorationText }}</small>
                                @if($isRemoved)
                                    <span class="badge bg-secondary ms-2">Eliminado</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif

<!-- Linked Services Section - Full Width, same style as details -->
@if($project->projectFares && $project->projectFares->count() > 0)
<div class="card mb-4">
   <div class="card-header d-flex justify-content-between align-items-center">
       <h5 class="mb-0">{{ __('Linked services') }}</h5>
       @can('update', $project)
       @if (! $project->isBudgetContentLocked())
       <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
           <i class="ti ti-plus ti-xs me-1"></i>{{ __('Vincular servicio') }}
       </button>
       @endif
       @endcan
   </div>
   <div class="card-body">
       <div class="table-responsive">
           <table class="table table-hover">
					<thead>
						<tr>
							<th class="col-1"></th>
							<th class="col-3">{{ __('Service') }}</th>
							<th class="col-3 text-center">{{ __('Languages') }}</th>
							<th class="col-1 text-center">{{ __('Quantity') }}</th>
							<th class="col-1">{{ __('Unit') }}</th>
							<th class="col-2 text-center">{{ __('Collaborators') }}</th>
							<th class="col-1 text-center">{{ __('Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($project->projectFares as $projectFare)
						@php
							// Check if there are collaborators that match this service requirements
							$hasMatchingCollaborator = false;

							if ($project->allCollaborators && $project->allCollaborators->count() > 0) {
								foreach ($project->allCollaborators as $collaborator) {
									// Check if collaborator has the required language combination
									$hasLanguageCombination = $collaborator->languageVariants->contains(function($variant) use ($projectFare) {
										return $variant->source_language_code === $projectFare->source_language_code
											&& $variant->target_language_code === $projectFare->target_language_code;
									});

									// Check if collaborator has the required fare/service
									$hasFare = $collaborator->fares->contains('id', $projectFare->fare_id);

									// If collaborator has both requirements, mark as matching
									if ($hasLanguageCombination && $hasFare) {
										$hasMatchingCollaborator = true;
										break;
									}
								}
							}
						@endphp
						<tr class="{{ $loop->last ? 'border-bottom-0' : '' }}">
							<td class="col-1 text-center">
								@if($hasMatchingCollaborator)
									<i class="ti ti-check text-success ti-lg"
									   data-bs-toggle="tooltip"
									   data-bs-placement="top"
									   title="Hay colaboradores asignados que cumplen con los requisitos"></i>
								@else
									<i class="ti ti-alert-triangle text-warning ti-lg"
									   data-bs-toggle="tooltip"
									   data-bs-placement="top"
									   title="No hay colaboradores asignados que cumplan con esta combinación de idioma y servicio"></i>
								@endif
							</td>
							<td class="col-3">
								<div class="mb-1">
									<strong>{{ $projectFare->fare->name ?? 'N/A' }}</strong>
									@if($projectFare->fare && $projectFare->fare->type)
										<br><small class="text-muted">{{ $projectFare->fare->type->name }}</small>
									@endif
								</div>
							</td>
							<td class="col-3 text-center">
								<x-language-combination-badge
									:sourceLanguage="$projectFare->sourceLanguage"
									:targetLanguage="$projectFare->targetLanguage"
									:sourceLanguageCode="$projectFare->source_language_code"
									:targetLanguageCode="$projectFare->target_language_code"
								/>
							</td>
							<td class="col-1 text-center">
								<span class="badge bg-light text-dark">{{ $projectFare->quantity }}</span>
							</td>
							<td class="col-1">
								{{ $projectFare->unit }}
							</td>
							<td class="col-2 text-center">
								<a href="{{ route('project.select-collaborators', $project->id) }}?source_language={{ $projectFare->source_language_code }}&target_language={{ $projectFare->target_language_code }}&service={{ $projectFare->fare_id }}"
								   class="btn btn-sm btn-outline-success">
									<i class="ti ti-users ti-xs me-1"></i>Asociar
								</a>
							</td>
							<td class="col-1 text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    @can('update', $project)
                                    @if (! $project->isBudgetContentLocked())
                                    <a href="javascript:;" class="text-body me-2" data-bs-toggle="modal" data-bs-target="#serviceModal" data-action="edit" data-service-id="{{ $projectFare->id }}">
                                        <i class="ti ti-edit ti-sm"></i>
                                    </a>
                                    @endif
                                    @endcan
                                    @can('delete', $project)
                                    @if (! $project->isBudgetContentLocked())
                                    <a href="javascript:;" class="text-danger" onclick="deleteProjectService({{ $projectFare->id }})">
                                        <i class="ti ti-trash ti-sm"></i>
                                    </a>
                                    @endif
                                    @endcan
                                </div>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
	@else
	<div class="card mb-4">
		<div class="card-body text-center py-4">
			<i class="ti ti-settings ti-xl text-muted mb-3"></i>
			<h6 class="mb-2">{{ __('No linked services') }}</h6>
			<p class="text-muted mb-3">{{ __('This project has no linked services yet') }}</p>
       @can('update', $project)
       @if (! $project->isBudgetContentLocked())
		<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
			<i class="ti ti-plus me-1"></i>{{ __('Vincular servicio') }}
		</button>
       @endif
		@endcan
		</div>
	</div>
	@endif

@if ($project->isBudgetContentLocked())
@php
	$lockedStatusOptions = \App\Models\ProjectStatus::query()
		->whereIn('id', $project->allowedStatusIdsWhenLocked())
		->orderBy('id')
		->get();
@endphp
<div class="modal fade" id="projectStatusModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ __('Change status') }}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="POST" action="{{ route('project.update-status', $project->id) }}">
				@csrf
				@method('PATCH')
				<div class="modal-body">
					<p class="text-muted small mb-3">
						{{ __('This approved budget is locked. Only the project status can be changed.') }}
					</p>
					<div class="mb-3">
						<label for="locked-status-id" class="form-label">{{ __('Project Status') }}</label>
						<select id="locked-status-id" name="status_id" class="select2 form-select" data-placeholder="{{ __('Choose an option') }}" required>
							@foreach ($lockedStatusOptions as $status)
								<option value="{{ $status->id }}" @selected((int) $project->status_id === (int) $status->id)>
									{{ $status->translated_name }}
								</option>
							@endforeach
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
					<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif

<!-- Modal para agregar/editar servicio -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="serviceModalTitle">Agregar servicio</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="serviceForm">
				<div class="modal-body">
					<input type="hidden" id="service-id" name="service_id">
					<input type="hidden" id="project-id" name="project_id" value="{{ $project->id }}">

					<div class="row g-3">
						<div class="col-md-6">
							<x-variant-language-select
								name="source_language_code"
								id="source_language"
								label="Idioma origen (*)"
								:required="true"
								placeholder="Seleccionar idioma origen"
							/>
						</div>
						<div class="col-md-6">
							<x-variant-language-select
								name="target_language_code"
								id="target_language"
								label="Idioma destino (*)"
								:required="true"
								placeholder="Seleccionar idioma destino"
							/>
						</div>
						<div class="col-md-8">
							<label class="form-label">Tipo de servicio (*)</label>
							<select name="fare_id" id="fare_select" class="form-select" required>
								<option value="">Seleccionar servicio</option>
								@php
									$faresByType = \App\Models\Fare::with('type')
										->where(function($query) {
											$query->whereNull('team_id');
											if (auth()->check() && auth()->user()->currentTeam) {
												$query->orWhere('team_id', auth()->user()->currentTeam->id);
											}
										})
										->orderBy('name')
										->get()
										->groupBy(function($fare) {
											return $fare->type ? $fare->type->name : 'Sin categoría';
										});
								@endphp

								@foreach($faresByType as $typeName => $fareList)
									<optgroup label="{{ $typeName }}">
										@foreach($fareList as $fare)
											<option value="{{ $fare->id }}">{{ $fare->name }}</option>
										@endforeach
									</optgroup>
								@endforeach
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label">Cantidad (*)</label>
							<input type="number" name="quantity" id="quantity" class="form-control"
								   value="1" min="1" step="1" required>
						</div>
						<div class="col-md-6">
							<label class="form-label">Unidad (*)</label>
							<select name="unit" id="unit_select" class="form-select" required>
								<option value="">Primero selecciona un servicio</option>
							</select>
						</div>
					</div>

					<div class="mt-3">
						<div class="alert alert-warning d-none" id="duplicate-warning">
							<i class="ti ti-alert-triangle me-2"></i>
							Este servicio ya está agregado con la misma combinación de idiomas.
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="saveServiceBtn">
						<i class="ti ti-check me-1"></i>
						<span id="saveServiceText">Agregar servicio</span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection

@push('scripts')
<script>
	// Initialize tooltips
	document.addEventListener('DOMContentLoaded', function() {
		var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
		var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
			return new bootstrap.Tooltip(tooltipTriggerEl);
		});

		var $lockedStatus = $('#locked-status-id');
		if ($lockedStatus.length && $.fn.select2) {
			var $statusModal = $('#projectStatusModal');
			$lockedStatus.select2({
				dropdownParent: $statusModal,
				width: '100%',
				minimumResultsForSearch: Infinity
			});
			$statusModal.on('shown.bs.modal', function () {
				$lockedStatus.trigger('change.select2');
			});
		}

		var $timeModal = $('#projectTimeModal');
		if ($timeModal.length && $.fn.select2) {
			$('#project-time-task-id, #project-time-user-id').each(function () {
				var $el = $(this);
				if ($el.length) {
					$el.select2({
						dropdownParent: $timeModal,
						width: '100%',
						allowClear: ! $el.prop('required'),
						placeholder: $el.data('placeholder') || ''
					});
				}
			});
		}

		function projectTimerHeaders() {
			return {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Accept': 'application/json'
			};
		}

		$(document).on('click', '.project-start-timer', function () {
			var $btn = $(this);
			var taskId = $btn.data('task-id');
			$btn.prop('disabled', true);
			$.ajax({
				url: '{{ route("time.start") }}',
				method: 'POST',
				headers: projectTimerHeaders(),
				data: { task_id: taskId },
				success: function () {
					window.location.reload();
				},
				error: function (xhr) {
					$btn.prop('disabled', false);
					var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '{{ __("Could not start timer.") }}';
					if (typeof toastr !== 'undefined') {
						toastr.error(msg);
					} else {
						alert(msg);
					}
				}
			});
		});

		$(document).on('click', '.project-stop-timer', function () {
			var $btn = $(this);
			var timeId = $btn.data('time-id');
			$btn.prop('disabled', true);
			$.ajax({
				url: '/time/' + timeId + '/stop',
				method: 'POST',
				headers: projectTimerHeaders(),
				success: function () {
					window.location.reload();
				},
				error: function (xhr) {
					$btn.prop('disabled', false);
					var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '{{ __("Could not stop timer.") }}';
					if (typeof toastr !== 'undefined') {
						toastr.error(msg);
					} else {
						alert(msg);
					}
				}
			});
		});

		// Service modal functionality
		initializeServiceModal();
	});

	// Function to remove collaborator from project
	function removeCollaboratorFromProject(projectId, collaboratorId, collaboratorName) {
		Swal.fire({
			title: '¿Estás seguro?',
			text: `¿Deseas eliminar a ${collaboratorName} de este proyecto?`,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Sí, eliminar',
			cancelButtonText: 'Cancelar',
			customClass: {
				confirmButton: 'btn btn-danger me-3',
				cancelButton: 'btn btn-label-secondary'
			},
			buttonsStyling: false
		}).then(function (result) {
			if (result.isConfirmed) {
				$.ajax({
					url: `/project/${projectId}/remove-collaborator/${collaboratorId}`,
					type: 'DELETE',
					data: {
						_token: $('meta[name="csrf-token"]').attr('content'),
					},
					success: function (response) {
						toastr.success('Colaborador eliminado del proyecto exitosamente');
						// Reload the page to update the collaborator list
						setTimeout(() => {
							location.reload();
						}, 1000);
					},
					error: function (response) {
						Swal.fire({
							title: 'Error',
							text: response.responseJSON?.message || 'Ha ocurrido un error',
							icon: 'error',
							customClass: {
								confirmButton: 'btn btn-primary'
							},
							buttonsStyling: false
						});
					}
				});
			}
		});
	}

	// Function to delete project service
	function deleteProjectService(serviceId) {
		if (!confirm('¿Estás seguro de que deseas eliminar este servicio?')) {
			return;
		}

		const projectId = document.getElementById('project-id').value;

		fetch(`/project/${projectId}/service/${serviceId}`, {
			method: 'DELETE',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
			}
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				location.reload();
			} else {
				alert(data.message || 'Error al eliminar servicio');
			}
		})
		.catch(error => {
			console.error('Error deleting service:', error);
			alert('Error al eliminar servicio');
		});
	}

	// Service modal initialization
	function initializeServiceModal() {
		const serviceModal = document.getElementById('serviceModal');
		const serviceForm = document.getElementById('serviceForm');
		const duplicateWarning = document.getElementById('duplicate-warning');

		let editingServiceId = null;

		// Handle fare selection change for units
		document.getElementById('fare_select').addEventListener('change', function() {
			const fareId = this.value;
			const unitSelect = document.getElementById('unit_select');

			if (!fareId) {
				unitSelect.innerHTML = '<option value="">Primero selecciona un servicio</option>';
				return;
			}

			// Show loading state
			unitSelect.innerHTML = '<option value="">Cargando unidades...</option>';
			unitSelect.disabled = true;

			// Fetch units for the selected fare
			fetch(`/project/fare-units?fare_id=${fareId}`, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				unitSelect.innerHTML = '<option value="">Seleccionar unidad</option>';

				if (data.units && data.units.length > 0) {
					data.units.forEach(unit => {
						const option = document.createElement('option');
						option.value = unit.type;
						option.textContent = unit.label;
						unitSelect.appendChild(option);
					});
				} else {
					unitSelect.innerHTML = '<option value="">No hay unidades disponibles</option>';
				}
			})
			.catch(error => {
				console.error('Error loading units:', error);
				unitSelect.innerHTML = '<option value="">Error al cargar unidades</option>';
			})
			.finally(() => {
				unitSelect.disabled = false;
			});
		});

		// Handle form submission
		serviceForm.addEventListener('submit', function(e) {
			e.preventDefault();

			const formData = new FormData(serviceForm);
			const serviceData = {
				service_id: formData.get('service_id'),
				project_id: formData.get('project_id'),
				fare_id: formData.get('fare_id'),
				source_language_code: formData.get('source_language_code'),
				target_language_code: formData.get('target_language_code'),
				quantity: formData.get('quantity'),
				unit: formData.get('unit')
			};

			duplicateWarning.classList.add('d-none');

			// Determine if we're editing or adding
			if (editingServiceId) {
				updateService(serviceData);
			} else {
				addService(serviceData);
			}
		});

		// Handle modal show event
		serviceModal.addEventListener('show.bs.modal', function(event) {
			const button = event.relatedTarget;
			const action = button?.getAttribute('data-action');

			if (action === 'edit') {
				const serviceId = button.getAttribute('data-service-id');
				editService(serviceId);
			} else {
				// Reset form for new service
				resetForm();
			}
		});

		// Handle modal hide event
		serviceModal.addEventListener('hide.bs.modal', function() {
			resetForm();
		});

		function addService(serviceData) {
			const button = document.getElementById('saveServiceBtn');
			const originalText = button.innerHTML;

			button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Guardando...';
			button.disabled = true;

			fetch(`/project/${serviceData.project_id}/service`, {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
				},
				body: JSON.stringify(serviceData)
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Close modal and reload page
					const modal = bootstrap.Modal.getInstance(serviceModal);
					modal.hide();
					location.reload();
				} else {
					alert(data.message || 'Error al agregar servicio');
				}
			})
			.catch(error => {
				console.error('Error adding service:', error);
				alert('Error al agregar servicio');
			})
			.finally(() => {
				button.innerHTML = originalText;
				button.disabled = false;
			});
		}

		function updateService(serviceData) {
			const button = document.getElementById('saveServiceBtn');
			const originalText = button.innerHTML;

			button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Actualizando...';
			button.disabled = true;

			fetch(`/project/${serviceData.project_id}/service/${serviceData.service_id}`, {
				method: 'PUT',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
				},
				body: JSON.stringify(serviceData)
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Close modal and reload page
					const modal = bootstrap.Modal.getInstance(serviceModal);
					modal.hide();
					location.reload();
				} else {
					alert(data.message || 'Error al actualizar servicio');
				}
			})
			.catch(error => {
				console.error('Error updating service:', error);
				alert('Error al actualizar servicio');
			})
			.finally(() => {
				button.innerHTML = originalText;
				button.disabled = false;
			});
		}

		function editService(serviceId) {
			const projectId = document.getElementById('project-id').value;

			// Get service details from server
			fetch(`/project/${projectId}/services`, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					const service = data.services.find(s => s.id == serviceId);
					if (service) {
						editingServiceId = serviceId;

						// Update modal title
						document.getElementById('serviceModalTitle').textContent = 'Editar servicio';
						document.getElementById('saveServiceText').textContent = 'Actualizar servicio';

						// Fill form with service data
						document.getElementById('service-id').value = service.id;
						document.getElementById('source_language').value = service.source_language_code;
						document.getElementById('target_language').value = service.target_language_code;
						document.getElementById('fare_select').value = service.fare_id;
						document.getElementById('quantity').value = service.quantity;

						// Load units for the selected fare
						const fareSelect = document.getElementById('fare_select');
						fareSelect.dispatchEvent(new Event('change'));

						// Set unit after units are loaded
						setTimeout(() => {
							document.getElementById('unit_select').value = service.unit;
						}, 500);
					}
				}
			})
			.catch(error => {
				console.error('Error loading service:', error);
			});
		}

		function resetForm() {
			serviceForm.reset();
			editingServiceId = null;
			document.getElementById('service-id').value = '';
			document.getElementById('serviceModalTitle').textContent = 'Agregar servicio';
			document.getElementById('saveServiceText').textContent = 'Agregar servicio';
			document.getElementById('unit_select').innerHTML = '<option value="">Primero selecciona un servicio</option>';
			duplicateWarning.classList.add('d-none');
		}
	}
</script>
@endpush
