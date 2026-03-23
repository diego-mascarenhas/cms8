@extends('layouts/layoutMaster')

@section('title', __('Templates'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Templates') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Create and manage email templates with visual editor') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if(isset($data->id))
            <a href="{{ route('template.editor', $data->getHashedId()) }}" class="btn btn-info waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Visual Editor') }}
            </a>
        @endif
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ __('Template Information') }}</h5>
	<form class="card-body" action="{{ route('template.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-8">
				<x-input-general id="name" label="{{ __('Template Name') }} (*)" value="{{ old('name', $data->name ?? '') }}" maxlength="75" />
			</div>
			<div class="col-md-4">
				<div class="form-check form-switch mt-4">
					<input class="form-check-input" type="checkbox" id="status_id" name="status_id" value="1" {{ old('status_id', $data->status_id ?? 1) == 1 ? 'checked' : '' }}>
					<label class="form-check-label" for="status_id">
						<strong>{{ __('Active Template') }}</strong>
					</label>
				</div>
			</div>
		</div>

		@if(!isset($data->id))
		<div class="row g-3 mt-2">
			<div class="col-12">
				<label for="ai_prompt" class="form-label">{{ __('Generate with AI (optional)') }}</label>
				<textarea class="form-control" id="ai_prompt" name="ai_prompt" rows="3" maxlength="2000" placeholder="{{ __('E.g.: Welcome newsletter with logo, headline and CTA button') }}">{{ old('ai_prompt', '') }}</textarea>
				<div class="form-text">{{ __('Describe the template you want. On save, AI will generate the HTML and open the visual editor.') }}</div>
			</div>
		</div>
		@endif
		
		@if(isset($data->id))
		<div class="row g-3 mt-3">
			<div class="col-12">
				<div class="alert alert-info">
					<i class="ti ti-info-circle me-2"></i>
					<strong>{{ __('Template Created!') }}</strong> {{ __('Use the Visual Editor button above to design your email template with GrapesJS.') }}
				</div>
			</div>
		</div>
		@endif
		
		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">
					{{ isset($data->id) ? __('Update') : __('Create') }} {{ __('Template') }}
				</button>
				<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('template.index') }}'">{{ __('Cancel') }}</button>
			</div>
		</div>
	</form>
</div>
@endsection
