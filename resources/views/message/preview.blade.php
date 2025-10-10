<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: {{ $message ? $message->name : 'Message Preview' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .preview-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .preview-header {
            background: #6c5ce7;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            font-size: 16px;
            font-weight: 600;
        }
        .preview-info {
            background: #f1f3f4;
            padding: 10px 20px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
        }
        .preview-content {
            padding: 20px;
        }
        .preview-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 0 0 8px 8px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
        .close-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 18px;
            z-index: 1000;
        }
        .close-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <button class="close-btn" onclick="window.close()" title="Close Preview">&times;</button>

    <div class="preview-container">
        <div class="preview-header">
            📧 Email Preview: {{ $message ? $message->name : 'Message Preview' }}
        </div>

        @if($message && $sampleContact)
        <div class="preview-info">
            <strong>To:</strong> {{ $sampleContact->email ?? 'sample@example.com' }} |
            <strong>From:</strong> {{ auth()->user()->currentTeam->getOutgoingEmailConfig()['from_name'] ?? 'Sender' }} &lt;{{ auth()->user()->currentTeam->getOutgoingEmailConfig()['from_address'] ?? 'sender@example.com' }}&gt; |
            <strong>Subject:</strong> {{ $message->name }}
        </div>
        @endif

        <div class="preview-content">
            {!! $htmlContent !!}
        </div>

        <div class="preview-footer">
            This is a preview of how the email will appear to recipients.
            <br>Variables have been replaced with sample data.
        </div>
    </div>
</body>
</html>
