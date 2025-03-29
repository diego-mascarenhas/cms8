@extends('layouts/layoutMaster')

@section('title', isset($data->id) ? 'Edit Task' : 'Create Task')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/form-layouts.js') }}"></script>
    <script src="{{ asset('assets/js/cms-form-client.js') }}"></script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ isset($data->id) ? 'Edit Task' : 'Create Task' }}</h4>
            <p class="text-muted">{{ isset($data->id) ? 'Update organization task details' : 'Create a new organization task' }}</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ isset($data->id) ? route('organization.update', $data->id) : route('organization.store') }}" method="POST">
                        @csrf
                        @if(isset($data->id))
                            @method('PUT')
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label">Task Name</label>
                                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}" required />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id" class="form-label">Department</label>
                                    <select id="department_id" name="department_id" class="select2 form-select @error('department_id') is-invalid @enderror" data-allow-clear="false" required>
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department['id'] }}" @if (old('department_id', $data->department_id ?? '') == $department['id']) selected @endif>
                                                {{ $department['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="responsible_id" class="form-label">Responsible</label>
                                    <select id="responsible_id" name="responsible_id" class="select2 form-select @error('responsible_id') is-invalid @enderror" data-allow-clear="false" required>
                                        <option value="">Select Responsible</option>
                                        @foreach ($contacts as $contact)
                                            <option value="{{ $contact['id'] }}" @if (old('responsible_id', $data->responsible_id ?? '') == $contact['id']) selected @endif>
                                                {{ $contact['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('responsible_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="time_allocation" class="form-label">Time Allocation</label>
                                    <input type="text" id="time_allocation" name="time_allocation" class="form-control @error('time_allocation') is-invalid @enderror" value="{{ old('time_allocation', $data->time_allocation ?? '') }}" required />
                                    @error('time_allocation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="availability" class="form-label">Availability</label>
                                    <input type="text" id="availability" name="availability" class="form-control @error('availability') is-invalid @enderror" value="{{ old('availability', $data->availability ?? '') }}" />
                                    @error('availability')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4" required>{{ old('description', $data->description ?? '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 d-flex">
                                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ isset($data->id) ? 'Update' : 'Create' }}</button>
                                <button type="button" class="btn btn-label-secondary" onclick="window.location.href='{{ route('organization.index') }}'">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.select2').select2();
        });
    </script>
@endsection 