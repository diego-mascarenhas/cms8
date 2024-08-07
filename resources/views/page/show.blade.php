<!DOCTYPE html>
<html>
<head>
    <title>{{ $page->title }}</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style type="text/css">
        {!! $page->css !!}
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }
    </style>
</head>
<body>
    @if(Auth::check())
        <div class="dashboard-header">
            <span>Welcome, {{ Auth::user()->name }}!</span>
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Go to Dashboard</a>
        </div>
    @endif

    {!! $page->html !!}

    @include('layouts/sections/footer/footer-landing')

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>