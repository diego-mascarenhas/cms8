<x-mail::message>
# {{ $insight->headline }}

**{{ __('app.performance_insight_column_focus') }}:** {!! nl2br(e($insight->focus)) !!}

{{ $insight->message }}

@if (! empty($highlights))
<x-mail::panel>
@foreach ($highlights as $line)
- {{ $line }}
@endforeach
</x-mail::panel>
@endif

<x-mail::button :url="route('performance-insights.index')">
{{ __('app.performance_insights_menu') }}
</x-mail::button>

{{ __('app.performance_digest_email_footer') }}
</x-mail::message>
