<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Team Configuration Report</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #f8f9fa; padding: 20px; border-bottom: 2px solid #dee2e6; }
        .content { padding: 20px; }
        .summary { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .service { margin: 15px 0; padding: 10px; border-left: 4px solid #28a745; }
        .service.failed { border-left-color: #dc3545; }
        .service.skipped { border-left-color: #6c757d; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
        .icon { margin-right: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Team Configuration Report</h1>
        <h2>{{ html_entity_decode($teamResult['team_name'], ENT_QUOTES, 'UTF-8') }}</h2>
        <p><strong>Generated on:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>

    <div class="content">
        <div class="summary">
            <h3>Summary</h3>
            <p><span class="icon">✅</span> <strong>Passed:</strong> {{ $teamResult['summary']['passed'] }}</p>
            <p><span class="icon">❌</span> <strong>Failed:</strong> {{ $teamResult['summary']['failed'] }}</p>
            <p><span class="icon">⏭️</span> <strong>Skipped:</strong> {{ $teamResult['summary']['skipped'] }}</p>
        </div>

        @if($teamResult['summary']['failed'] > 0)
            <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="color: #721c24;">⚠️ ATTENTION REQUIRED</h3>
                <p><strong>{{ $teamResult['summary']['failed'] }} service(s) need your attention!</strong></p>
            </div>
        @else
            <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="color: #155724;">🎉 All configured services are working correctly!</h3>
            </div>
        @endif

        <h3>Service Details</h3>
        @foreach($teamResult['tests'] as $test)
            <div class="service {{ $test['status'] }}">
                <h4>
                    @if($test['status'] === 'passed')
                        <span class="icon">✅</span>
                    @elseif($test['status'] === 'failed')
                        <span class="icon">❌</span>
                    @else
                        <span class="icon">⏭️</span>
                    @endif
                    {{ strtoupper($test['service']) }}: {{ strtoupper($test['status']) }}
                </h4>
                <p><strong>Message:</strong> {{ $test['message'] }}</p>
                @if(isset($test['details']) && ($test['status'] === 'failed' || !$failuresOnly))
                    <p><strong>Details:</strong> {{ $test['details'] }}</p>
                @endif
            </div>
        @endforeach

        @if($teamResult['summary']['failed'] > 0)
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="color: #856404;">Next Steps</h3>
                <ol>
                    <li>Log into your team settings to review failed configurations</li>
                    <li>Update the credentials or settings as needed</li>
                    <li>Test the connections manually to verify fixes</li>
                    <li>The system will automatically re-check these configurations daily</li>
                </ol>
                <p><strong>Support:</strong> If you need help resolving these issues, please contact your system administrator.</p>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated report from your Team Configuration Monitoring System.</p>
        <p><strong>Report ID:</strong> {{ $teamResult['team_id'] }}-{{ now()->format('YmdHis') }}</p>
    </div>
</body>
</html>
