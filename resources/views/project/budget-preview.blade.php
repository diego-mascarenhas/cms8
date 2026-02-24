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
            foreach ($suggestedTasks as $t) {
                $title = $t['title'] ?? '—';
                $level = isset($t['resource_level']) && $t['resource_level'] !== '' ? $t['resource_level'] : '';
                $levelPart = $level ? ' (' . $level . ')' : '';
                $price = isset($t['unit_price']) && $t['unit_price'] !== '' && $t['unit_price'] !== null ? (float) $t['unit_price'] : null;
                $priceStr = $price !== null ? number_format($price, 2, ',', '.') . ' €' : '—';
                if ($price !== null) {
                    $total += $price;
                }
                $lines[] = '• ' . $title . $levelPart . '. ' . __('Value') . ': ' . $priceStr;
            }
            $lines[] = '';
            $lines[] = __('Total') . ': ' . number_format($total, 2, ',', '.') . ' €';
            $summaryText = implode("\n", $lines);
        @endphp
        <pre class="bg-light border rounded p-3 font-monospace mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ $summaryText }}</pre>
    @else
        <p class="text-muted">{{ __('No budget breakdown available.') }}</p>
    @endif
</div>
@endsection
