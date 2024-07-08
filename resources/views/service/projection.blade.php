@extends('layouts/layoutMaster')

@section('title', 'Billing Projection')

@section('content')
<div class="container">
    <h1>Billing Projection</h1>
    <table class="table table-bordered">
        <thead>
            <tr class="text-center">
                <th>Month</th>
                <th class="text-end">Earnings</th>
                <th class="text-end">Expenses</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projectionData as $month => $data)
                <tr>
                    <td class="text-center">{{ $month }}</td>
                    <td class="text-end">{{ number_format($data['earnings'], 2, ',' ,'.') }}</td>
                    <td class="text-end">{{ number_format($data['expenses'], 2, ',' ,'.') }}</td>
                </tr>
            @endforeach
            <tr>
                <th class="text-center"><strong>Total Annual</strong></th>
                <th class="text-end"><strong>{{ number_format($totalEarnings, 2, ',' ,'.') }}</strong></th>
                <th class="text-end"><strong>{{ number_format($totalExpenses, 2, ',' ,'.') }}</strong></th>
            </tr>
        </tbody>
    </table>
</div>
@endsection
