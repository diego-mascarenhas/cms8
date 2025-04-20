@extends('layouts.layoutMaster')

@section('title', 'OVH Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">OVH Cloud API Dashboard</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Invoices</h5>
                                    <p>Get all your OVH invoices</p>
                                    <button class="btn btn-primary fetch-invoices">
                                        Fetch Invoices
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Services</h5>
                                    <p>Get all your OVH contracted services</p>
                                    <button class="btn btn-primary fetch-services">
                                        Fetch Services
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Invoices Results</h5>
                                </div>
                                <div class="card-body">
                                    <div class="invoices-result">
                                        <p>No data fetched yet</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Services Results</h5>
                                </div>
                                <div class="card-body">
                                    <div class="services-result">
                                        <p>No data fetched yet</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(function() {
        $('.fetch-invoices').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
            
            $.ajax({
                url: '{{ route("ovh.invoices") }}',
                method: 'GET',
                success: function(response) {
                    const invoicesDiv = $('.invoices-result');
                    invoicesDiv.empty();
                    
                    if (response.status === 'success') {
                        const table = $('<table class="table table-striped">');
                        const thead = $('<thead>').appendTo(table);
                        const tbody = $('<tbody>').appendTo(table);
                        
                        $('<tr>').appendTo(thead)
                            .append('<th>Invoice ID</th>')
                            .append('<th>Date</th>')
                            .append('<th>Amount</th>')
                            .append('<th>Status</th>');
                            
                        response.data.forEach(function(invoice) {
                            $('<tr>').appendTo(tbody)
                                .append(`<td>${invoice.id}</td>`)
                                .append(`<td>${invoice.date}</td>`)
                                .append(`<td>${invoice.priceWithTax.value} ${invoice.priceWithTax.currencyCode}</td>`)
                                .append(`<td>${invoice.status}</td>`);
                        });
                        
                        invoicesDiv.append(
                            $('<div class="alert alert-success">').text(`Successfully fetched ${response.count} invoices`)
                        ).append(table);
                    } else {
                        invoicesDiv.append(
                            $('<div class="alert alert-danger">').text('Failed to fetch invoices')
                        );
                    }
                    
                    button.prop('disabled', false).text('Fetch Invoices');
                },
                error: function(xhr, status, error) {
                    $('.invoices-result').empty().append(
                        $('<div class="alert alert-danger">').text(`Error: ${xhr.responseJSON?.message || error}`)
                    );
                    button.prop('disabled', false).text('Fetch Invoices');
                }
            });
        });
        
        $('.fetch-services').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
            
            $.ajax({
                url: '{{ route("ovh.services") }}',
                method: 'GET',
                success: function(response) {
                    const servicesDiv = $('.services-result');
                    servicesDiv.empty();
                    
                    if (response.status === 'success') {
                        const table = $('<table class="table table-striped">');
                        const thead = $('<thead>').appendTo(table);
                        const tbody = $('<tbody>').appendTo(table);
                        
                        $('<tr>').appendTo(thead)
                            .append('<th>Service ID</th>')
                            .append('<th>Name</th>')
                            .append('<th>Status</th>')
                            .append('<th>Expiration</th>');
                            
                        response.data.forEach(function(service) {
                            $('<tr>').appendTo(tbody)
                                .append(`<td>${service.id}</td>`)
                                .append(`<td>${service.domain}</td>`)
                                .append(`<td>${service.status}</td>`)
                                .append(`<td>${service.expiration}</td>`);
                        });
                        
                        servicesDiv.append(
                            $('<div class="alert alert-success">').text(`Successfully fetched ${response.count} services`)
                        ).append(table);
                    } else {
                        servicesDiv.append(
                            $('<div class="alert alert-danger">').text('Failed to fetch services')
                        );
                    }
                    
                    button.prop('disabled', false).text('Fetch Services');
                },
                error: function(xhr, status, error) {
                    $('.services-result').empty().append(
                        $('<div class="alert alert-danger">').text(`Error: ${xhr.responseJSON?.message || error}`)
                    );
                    button.prop('disabled', false).text('Fetch Services');
                }
            });
        });
    });
</script>
@endsection 