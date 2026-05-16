<x-mail::message>
# {{ $insight->headline }}

**{{ __('app.performance_insight_column_focus') }}:** {!! nl2br(e($insight->focus)) !!}

{{ $insight->message }}

@if (! empty($userActivity))
**{{ __('app.performance_digest_email_activity_heading') }}**

- {{ __('app.performance_digest_email_activity_interactions', ['count' => (int) ($userActivity['interactions_count'] ?? 0)]) }}
- {{ __('app.performance_digest_email_activity_calls', ['minutes' => (int) round((float) ($userActivity['call_minutes'] ?? 0))]) }}
- {{ __('app.performance_digest_email_activity_tasks_done', ['count' => (int) ($userActivity['tasks_done'] ?? 0)]) }}
- {{ __('app.performance_insight_notification_ratio', ['ratio' => number_format((float) $insight->performance_ratio, 2)]) }}
@endif

@if (! empty($highlights))
**{{ __('app.performance_insight_notification_highlights') }}**

<x-mail::panel>
@foreach ($highlights as $line)
- {{ $line }}
@endforeach
</x-mail::panel>
@endif

@if ($tasksModuleEnabled && ! empty($dailyTasks))
**{{ __('app.performance_digest_email_tasks_heading') }}**

@foreach ($dailyTasks as $task)
- @if (! empty($task['is_overdue']))**{{ __('app.performance_digest_task_overdue') }}:** @elseif (! empty($task['is_due_today']))**{{ __('app.performance_digest_task_due_today') }}:** @endif{{ $task['title'] }} — {{ $task['due_label'] }} ({{ $task['status'] }})
@endforeach

<x-mail::button :url="route('task.index')">
{{ __('Tasks') }}
</x-mail::button>
@endif

<x-mail::button :url="route('performance-insights.index', ['insight_date' => $insight->insight_date->toDateString()])">
{{ __('app.performance_insights_menu') }}
</x-mail::button>

{{ __('app.performance_digest_email_footer') }}
</x-mail::message>
