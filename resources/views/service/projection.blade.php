@extends('layouts/layoutMaster')

@section('content')
<div class="container">
    <h1>Billing Projection</h1>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Month</th>
                <th>Earnings</th>
                <th>Expenses</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projectionData as $month => $data)
                <tr>
                    <td>{{ $month }}</td>
                    <td>{{ number_format($data['earnings'], 2) }}</td>
                    <td>{{ number_format($data['expenses'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
