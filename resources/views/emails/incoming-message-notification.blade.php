<!DOCTYPE html>
<html>
<head>
    <title>New Message Received</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }
        .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .message-info {
            margin-bottom: 20px;
        }
        .message-content {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .footer {
            font-size: 12px;
            color: #777;
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Message Received</h2>
        </div>
        
        <div class="message-info">
            <p><strong>From:</strong> {{ $conversation->from }}</p>
            <p><strong>Channel:</strong> {{ ucfirst($conversation->channel) }}</p>
            <p><strong>Time:</strong> {{ $conversation->created_at->format('Y-m-d H:i:s') }}</p>
        </div>
        
        <div class="message-content">
            <p><strong>Message:</strong></p>
            <p>{{ $conversation->body }}</p>
        </div>
        
        @if($conversation->media)
            <div class="media-info">
                <p><strong>Media Attachments:</strong> {{ count($conversation->media) }}</p>
            </div>
        @endif
        
        <p>To respond, please login to the system.</p>
        
        <div class="footer">
            <p>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html> 