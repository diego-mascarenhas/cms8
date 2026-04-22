<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure Password Share</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7fb; margin: 0; padding: 24px; }
        .card { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb; }
        .muted { color: #6b7280; }
        .alert { padding: 12px; border-radius: 6px; }
        .warning { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
        .danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="card">
        @if($status === 'ok')
            <h2>Secure password share</h2>
            <p class="muted">This link is one-time. The secret is now consumed after this view.</p>
            <p><strong>Name:</strong> {{ $name }}</p>
            <p><strong>Username:</strong> {{ $username ?: '—' }}</p>
            <p><strong>Password:</strong> <code>{{ $password }}</code></p>
            <p><strong>URL:</strong> {{ $url ?: '—' }}</p>
            <p><strong>Notes:</strong> {{ $notes ?: '—' }}</p>
        @elseif($status === 'expired')
            <div class="alert warning">This secure link has expired.</div>
        @elseif($status === 'consumed')
            <div class="alert warning">This secure link was already used and is no longer available.</div>
        @else
            <div class="alert danger">This secure link is invalid.</div>
        @endif
    </div>
</body>
</html>
