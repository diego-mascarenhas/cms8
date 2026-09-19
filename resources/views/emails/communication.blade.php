<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $communication->subject }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .message-content {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="message-content">
        {!! nl2br(e($communication->message)) !!}
    </div>

    <div class="footer">
        <p>{{ __('This is an automated message. Please do not reply to this email.') }}</p>
    </div>
    <img src="{{ $communication->trackingUrl() }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">
</body>
</html>
