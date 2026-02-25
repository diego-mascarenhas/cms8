@extends('layouts/blankLayout')

@section('title', __('Budget preview'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h5 class="mb-3">{{ __('Budget preview') }}</h5>
    <p class="text-muted small mb-3">{{ __('Summary of requested quote and values, ready to copy into an email.') }}</p>
    @if (!empty($suggestedTasks))
        @php
            $lines = [__('Summary of requested quote and values'), ''];
            $total = 0;
            $totalHours = 0;
            $strikethrough = function ($text) {
                $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY);
                return implode("\u{0336}", $chars) . "\u{0336}";
            };
            foreach ($suggestedTasks as $t) {
                $title = $t['title'] ?? '—';
                $included = $t['included'] ?? true;
                if ($included) {
                    $lines[] = '• ' . $title;
                    $price = isset($t['unit_price']) && $t['unit_price'] !== '' && $t['unit_price'] !== null ? (float) $t['unit_price'] : null;
                    if ($price !== null) {
                        $total += $price;
                    }
                    $hours = isset($t['estimated_hours']) && $t['estimated_hours'] !== '' && $t['estimated_hours'] !== null ? (float) $t['estimated_hours'] : 0;
                    $totalHours += $hours;
                } else {
                    $lines[] = '• ' . $strikethrough($title);
                }
            }
            $lines[] = '';
            $totalRounded = (int) round($total);
            $lines[] = __('Total') . ': ' . number_format($totalRounded, 0, '', '.') . '€ + ' . __('I.V.A.');
            $weeks = $totalHours > 0 ? (int) ceil($totalHours / 40) : 0;
            $lines[] = '';
            $lines[] = __('Estimated development time, :weeks weeks after the budget has been confirmed.', ['weeks' => $weeks]);
            $summaryText = implode("\n", $lines);
        @endphp
        <pre class="bg-light border rounded p-3 font-monospace mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ $summaryText }}</pre>
    @else
        <p class="text-muted">{{ __('No budget breakdown available.') }}</p>
    @endif
</div>
@endsection
