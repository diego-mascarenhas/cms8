@extends('layouts/layoutMaster')

@section('title', __('app.Field Configuration'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('app.Field Configuration') }}</h4>
        <p class="text-muted">{{ __('app.Configure additional fields for content sections') }}</p>
    </div>
    @can('create', \App\Models\ContentFieldConfig::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('content-field-configs.create', ['section_id' => request('section_id')]) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('app.New Field') }}
        </a>
    </div>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4">
                <form method="get">
                    <label for="section_id" class="form-label">{{ __('app.Filter by Section') }}</label>
                    <select name="section_id" id="section_id" class="form-select select2" onchange="this.form.submit()">
                        <option value="">{{ __('app.All Sections') }}</option>
                        @foreach($sectionCategories as $sectionCategory)
                            <option value="{{ $sectionCategory->id }}" {{ request('section_id') == $sectionCategory->id ? 'selected' : '' }}>
                                {{ $sectionCategory->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('app.Section') }}</th>
                        <th>{{ __('app.Field Key') }}</th>
                        <th>{{ __('app.Field Label') }}</th>
                        <th>{{ __('app.Type') }}</th>
                        <th>{{ __('app.Required') }}</th>
                        <th>{{ __('app.Active') }}</th>
                        <th>{{ __('app.Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fieldConfigs as $config)
                        <tr>
                            <td>{{ $config->sectionCategory->name }}</td>
                            <td><code>{{ $config->field_key }}</code></td>
                            <td>{{ $config->field_label }}</td>
                            <td>{{ ucfirst($config->field_type) }}</td>
                            <td>
                                @if($config->required)
                                    <span class="badge bg-label-danger">{{ __('app.Yes') }}</span>
                                @else
                                    <span class="badge bg-label-secondary">{{ __('app.No') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($config->is_active)
                                    <span class="badge bg-label-success">{{ __('app.Active') }}</span>
                                @else
                                    <span class="badge bg-label-warning">{{ __('app.Inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    @can('update', $config)
                                    <a href="{{ route('content-field-configs.edit', $config->id) }}" class="text-body">
                                        <i class="ti ti-edit ti-sm"></i>
                                    </a>
                                    @endcan
                                    @can('delete', $config)
                                    <a href="#" class="text-danger" onclick="deleteConfig({{ $config->id }})">
                                        <i class="ti ti-trash ti-sm"></i>
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">{{ __('app.No field configurations found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(function() {
    $('#section_id').select2();
});

function deleteConfig(id) {
    Swal.fire({
        title: '{{ __("app.Are you sure?") }}',
        text: '{{ __("app.This action cannot be undone.") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("app.Yes, delete it") }}',
        cancelButtonText: '{{ __("app.Cancel") }}',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("content-field-configs.destroy", ":id") }}'.replace(':id', id),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    Swal.fire({
                        title: '{{ __("app.Deleted") }}',
                        text: response.success,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        title: '{{ __("app.Error") }}',
                        text: xhr.responseJSON?.error || '{{ __("app.Failed to delete") }}',
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
</script>
@endsection
