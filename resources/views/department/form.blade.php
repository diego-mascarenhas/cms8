@extends('layouts/layoutMaster')

@section('title', $department->id ? __('Edit department') : __('Create department'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ $department->id ? __('Edit department') : __('Create department') }}</h4>
            <p class="text-muted">{{ __('Department name and color') }}</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ $department->id ? route('department.update', $department) : route('department.store') }}" method="POST">
                @csrf
                @if ($department->id)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('Name') }} (*)</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $department->name) }}" required />
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Color') }} (*)</label>
                        <input type="hidden" name="color" id="color_value" value="{{ old('color', $department->color ?? '#feff9c') }}" />
                        @php
                            $departmentColor = old('color', $department->color ?? '#feff9c');
                            $primaryColors = ['#feff9c', '#ffc988', '#b4ff88', '#88e1ff', '#696cff', '#ff4d4d', '#71dd37', '#03c3ec'];
                            if ($departmentColor && !in_array($departmentColor, $primaryColors)) {
                                array_unshift($primaryColors, $departmentColor);
                            }
                        @endphp
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @foreach ($primaryColors as $hex)
                                <button type="button" class="department-color-pill border rounded-circle p-0 {{ $departmentColor === $hex ? 'selected' : '' }}" style="width: 2rem; height: 2rem; background-color: {{ $hex }}; border-width: 2px !important;" data-color="{{ $hex }}" title="{{ $hex }}" aria-label="{{ __('Select color') }} {{ $hex }}"></button>
                            @endforeach
                        </div>
                        @error('color')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $department->id ? __('Update') : __('Create') }}</button>
                        <a href="{{ route('department.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('page-style')
<style>
    .department-color-pill { cursor: pointer; transition: transform 0.15s ease; }
    .department-color-pill:hover { transform: scale(1.1); }
    .department-color-pill.selected { border-color: #333 !important; box-shadow: 0 0 0 2px #333; }
</style>
@endsection

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.department-color-pill').forEach(function(pill) {
            pill.addEventListener('click', function() {
                document.querySelectorAll('.department-color-pill').forEach(function(p) { p.classList.remove('selected'); });
                this.classList.add('selected');
                document.getElementById('color_value').value = this.getAttribute('data-color');
            });
        });
    });
</script>
@endsection
