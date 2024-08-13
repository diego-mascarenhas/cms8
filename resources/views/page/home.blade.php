<!DOCTYPE html>
<html>
<head>
    <title>{{ $page->title }}</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    @if(Auth::check())
        <div class="dashboard-header d-flex justify-content-end m-3">
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm me-2">Go to Dashboard</a>
        </div>
    @else
        <div class="dashboard-header d-flex justify-content-end m-3">
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm me-2">Login</a>
        </div>
    @endif

    {!! $page->html !!}

    @include('layouts/sections/footer/footer-landing')
</body>
</html>