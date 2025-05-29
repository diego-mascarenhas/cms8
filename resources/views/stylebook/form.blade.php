@extends('layouts/layoutMaster')

@section('title', __('Style Books'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
    $(function() {
        // Initialize Select2 if available
        if ($.fn.select2) {
            $('#language').select2();
        }
        
        // Initialize flatpickr for date
        flatpickr('#date', {
            dateFormat: 'Y-m-d'
        });
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Style Books') }}/</span> {{ isset($stylebook->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Manage translation style guides') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('stylebook.destroy')
            @if(isset($stylebook->id))
                <a href="javascript:void(0)" onclick="if(confirm('{{ __('Are you sure you want to delete this style book?') }}')) { document.getElementById('delete-form').submit(); }" class="btn btn-danger waves-effect waves-light">
                    <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
                </a>
                <form id="delete-form" method="POST" action="{{ route('stylebook.destroy', $stylebook->id) }}" style="display: none;">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endcan
    </div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ __('Style Book Details') }}</h5>
	<form class="card-body" action="{{ isset($stylebook) ? route('stylebook.update', $stylebook->id) : route('stylebook.store') }}" method="POST" enctype="multipart/form-data">
		@csrf
		@if(isset($stylebook))
            @method('PUT')
        @endif
		
		<div class="row g-3">
			<div class="col-md-6">
				<x-input-general id="name" label="{{ __('Name') }} (*)" value="{{ old('name', $stylebook->name ?? '') }}" />
			</div>
			<div class="col-md-3">
				<x-language-select 
                    name="language"
                    id="language"
                    label="{{ __('Language') }} (*)" 
                    :value="old('language', $stylebook->language ?? '')" 
                />
			</div>
			<div class="col-md-3">
				<x-input-date id="date" label="{{ __('Date') }} (*)" 
					value="{{ old('date', isset($stylebook->date) ? $stylebook->date->format('Y-m-d') : '') }}" />
			</div>

            <div class="col-md-12">
                <div class="form-group mb-3">
                    <label for="file" class="form-label">{{ __('File') }} {{ isset($stylebook) ? '' : '(*)' }}</label>
                    <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror" {{ isset($stylebook) ? '' : 'required' }}>
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if(isset($stylebook) && $stylebook->file)
                        <div class="mt-2">
                            <a href="{{ asset('storage/' . $stylebook->file) }}" target="_blank" class="btn btn-sm btn-label-secondary">
                                <i class="ti ti-file-download me-1"></i>{{ __('Download current file') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
		</div>
		
		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
				<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('stylebook.index') }}'">{{ __('Cancel') }}</button>
			</div>
		</div>
	</form>
</div>
@endsection 