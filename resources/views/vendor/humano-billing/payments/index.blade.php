@extends('layouts/layoutMaster')

@section('title', __('Payments'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Payments') }}</h4>
        <p class="text-muted">{{ __('Manage your payments') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-datatable table-responsive">
        <table class="table" id="payments-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Enterprise') }}</th>
                    <th>{{ __('Invoice') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
        </table>
    </div>
    </div>

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn.DataTable.isDataTable('#payments-table')) {
        $('#payments-table').DataTable().destroy();
    }
    const isEs = '{{ app()->getLocale() }}' === 'es';
    const dtLangEs = {
        processing: "Procesando...",
        zeroRecords: "No se encontraron registros",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "Mostrando 0 a 0 de 0 registros",
        infoFiltered: "(filtrado de _MAX_ registros totales)",
        search: "Buscar:",
        paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
    };
    $('#payments-table').DataTable({
        processing: true,
        serverSide: false,
        lengthChange: false,
        ajax: {
            url: '{{ route('payments.data') }}',
            dataSrc: 'data'
        },
        language: isEs ? dtLangEs : undefined,
        columns: [
            { data: 'date' },
            { data: 'enterprise_id' },
            { data: 'invoice_id' },
            { data: 'type_id' },
            { data: 'amount' },
            { data: 'status' }
        ]
    });
});
</script>
@endsection
@endsection


