@extends('layouts/layoutMaster')

@section('title', __('Software'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
    $(function() {
        if ($.fn.select2) {
            $('#category_id').select2();
        }

        // Delete functionality with form submission
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            const form = $(this).closest('form');

            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    // Mostrar indicador de carga
                    Swal.fire({
                        title: 'Eliminando...',
                        text: 'Por favor espere',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit the form
                    form.submit();
                }
            });
        });
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Softwares') }}/</span> {{ isset($software) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Software Management') }}</p>
    </div>
    @if(isset($software))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <form action="{{ route('software.destroy', $software->id) }}" method="POST" style="display: inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger waves-effect waves-light btn-delete">
                <i class="ti ti-trash me-1"></i> Eliminar
            </button>
        </form>
    </div>
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Software') }}</h5>
    <form class="card-body" action="{{ isset($software) ? route('software.update', $software->id) : route('software.store') }}" method="POST">
        @csrf
        @if(isset($software))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <x-input-general id="name" label="Nombre (*)" value="{{ old('name', $software->name ?? '') }}" />
            </div>

            <div class="col-md-6">
                <label class="form-label" for="category_id">Categoría</label>
                <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror">
                    <option value="">Seleccione categoría (opcional)</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ isset($software) && $software->category_id == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('software.index') }}'">Cancelar</button>
            </div>
        </div>
    </form>
</div>
@endsection
