@extends('layouts/layoutMaster')

@section('title', __('Invoices'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Invoices') }}</h4>
        <p class="text-muted">{{ __('Manage your invoices and billing') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-datatable table-responsive">
        <table class="table" id="invoices-table">
            <thead>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Enterprise') }}</th>
                    <th>{{ __('Operation') }}</th>
                    <th>{{ __('Total') }}</th>
                    <th>{{ __('Discount') }}</th>
                    <th>{{ __('Balance') }}</th>
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
    if ($.fn.DataTable.isDataTable('#invoices-table')) {
        $('#invoices-table').DataTable().destroy();
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
    $('#invoices-table').DataTable({
        processing: true,
        serverSide: false,
        lengthChange: false,
        ajax: {
            url: '{{ route('invoices.data') }}',
            dataSrc: 'data'
        },
        language: isEs ? dtLangEs : undefined,
        columns: [
            { data: 'number' },
            { data: 'date' },
            { data: 'enterprise_id' },
            { data: 'operation' },
            { data: 'total_amount' },
            { data: 'discount' },
            { data: 'balance' },
            { data: 'status' }
        ]
    });
});
</script>
@endsection
@endsection


