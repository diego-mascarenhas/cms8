@extends('layouts/layoutMaster')

@section('title', $store->id ? __('Editar tienda') : __('Crear tienda'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
    <script>
        $(document).on('click', '.btn-delete-store', function(e) {
            e.preventDefault();
            var form = document.getElementById('delete-store-form');
            Swal.fire({
                title: '{{ __("¿Estás seguro?") }}',
                text: "{{ __('Esta acción enviará la tienda a la papelera (soft delete).') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '{{ __("Sí, eliminar") }}',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-danger ms-1'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });
        });
    </script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ $store->id ? __('Editar tienda') : __('Crear tienda') }}</h4>
            <p class="text-muted">{{ __('Nombre de la tienda y detalles de sucursal') }}</p>
        </div>
        @if ($store->id && ! $store->is_main)
            <div class="mt-3 mt-md-0">
                <form id="delete-store-form" action="{{ route('store.destroy', $store->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-danger waves-effect waves-light btn-delete-store">
                        <i class="ti ti-trash me-1"></i> {{ __('Eliminar') }}
                    </button>
                </form>
            </div>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ $store->id ? route('store.update', $store->id) : route('store.store') }}" method="POST">
                @csrf
                @if ($store->id)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('Name') }} (*)</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $store->name) }}" required />
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="code" class="form-label">{{ __('Code') }}</label>
                        <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $store->code) }}" />
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-12">
                        <label for="address" class="form-label">{{ __('Address') }}</label>
                        <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $store->address) }}" />
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label">{{ __('Status') }} (*)</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="1" {{ (string) old('status', (int) $store->status) === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                            <option value="0" {{ (string) old('status', (int) $store->status) === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="is_main" name="is_main" value="1" {{ old('is_main', $store->is_main) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_main">{{ __('Marcar como tienda principal') }}</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $store->id ? __('Update') : __('Create') }}</button>
                        <a href="{{ route('store.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
