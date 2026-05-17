@props(['subject' => ''])

@php
    $headlineParts = \App\Models\UserDailyPerformanceInsight::splitHeadlineWordAndTrailingEmoji($subject);
@endphp

@if($headlineParts['emoji'] !== '')
    <span aria-hidden="true">{{ $headlineParts['emoji'] }}</span>
@endif
{{ e($headlineParts['text']) }}
