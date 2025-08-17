# {{ html_entity_decode($teamResult['team_name'], ENT_QUOTES, 'UTF-8') }} - Configuration Report

Generated on: {{ now()->format('Y-m-d H:i:s') }}

## Summary
✅ Passed: {{ $teamResult['summary']['passed'] }}
❌ Failed: {{ $teamResult['summary']['failed'] }}
⏭️ Skipped: {{ $teamResult['summary']['skipped'] }}

@if($teamResult['summary']['failed'] > 0)
⚠️  ATTENTION REQUIRED: {{ $teamResult['summary']['failed'] }} service(s) need your attention!
@else
🎉 All configured services are working correctly!
@endif

## Service Details

@foreach($teamResult['tests'] as $test)
{{ $test['status'] === 'passed' ? '✅' : ($test['status'] === 'failed' ? '❌' : '⏭️') }} {{ strtoupper($test['service']) }}: {{ strtoupper($test['status']) }}
   Message: {{ $test['message'] }}
@if(isset($test['details']) && ($test['status'] === 'failed' || !$failuresOnly))
   Details: {{ $test['details'] }}
@endif

@endforeach

@if($teamResult['summary']['failed'] > 0)
## Next Steps
1. Log into your team settings to review failed configurations
2. Update the credentials or settings as needed
3. Test the connections manually to verify fixes
4. The system will automatically re-check these configurations daily

## Support
If you need help resolving these issues, please contact your system administrator.
@endif

---
This is an automated report from your Team Configuration Monitoring System.
Report ID: {{ $teamResult['team_id'] }}-{{ now()->format('YmdHis') }}
