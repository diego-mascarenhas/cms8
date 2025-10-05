@extends('layouts/layoutMaster')

@section('title', 'Edit Account')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Edit Account</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('account.update', $team->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label" for="name">Account Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ $team->name }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Core Modules</label>
                            <p class="text-muted small">These are essential modules that are enabled by default but can be disabled if needed.</p>
                            <div class="row">
                                @foreach($coreModules as $module)
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="modules[]"
                                                value="{{ $module->key }}"
                                                id="module_{{ $module->key }}"
                                                {{ $team->hasModule($module->key) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="module_{{ $module->key }}">
                                                <i class="ti ti-{{ $module->icon }} me-2"></i>
                                                {{ $module->name }}
                                                @if($module->description)
                                                    <small class="text-muted d-block">{{ $module->description }}</small>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Additional Modules</label>
                            <p class="text-muted small">These are optional modules that can be enabled based on your needs.</p>

                            @foreach($additionalModules as $groupKey => $modules)
                                @php
                                    $groupInfo = $groupLabels[$groupKey] ?? ['name' => 'Other Modules', 'icon' => 'puzzle', 'description' => 'Other modules'];
                                @endphp

                                {{-- Group Header --}}
                                <div class="mb-3 mt-4">
                                    <h6 class="text-uppercase text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="ti ti-{{ $groupInfo['icon'] }} me-2"></i>{{ $groupInfo['name'] }}
                                    </h6>
                                    <p class="text-muted small mb-2">{{ $groupInfo['description'] }}</p>
                                    <hr class="mt-0 mb-3">
                                </div>

                                {{-- Modules in this group --}}
                                <div class="row mb-4">
                                    @foreach($modules as $module)
                                        <div class="col-md-4 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="modules[]"
                                                    value="{{ $module->key }}"
                                                    id="module_{{ $module->key }}"
                                                    {{ $team->hasModule($module->key) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="module_{{ $module->key }}">
                                                    <i class="ti ti-{{ $module->icon }} me-2"></i>
                                                    {{ $module->name }}
                                                    @if($module->description)
                                                        <small class="text-muted d-block">{{ $module->description }}</small>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="{{ route('account-management') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
