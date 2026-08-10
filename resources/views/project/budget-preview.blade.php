@php
    $budgetService = app(\App\Services\ProjectBudgetSpecService::class);
    $projectName = trim((string) ($project->real_name ?: $project->name));
    $clientName = trim((string) (optional($project->enterprise)->name ?? ''));
    $dimension = trim((string) data_get($project->data, 'dimension', ''));
    $estimatedTimes = trim((string) data_get($project->data, 'estimated_times', ''));
    $resources = trim((string) data_get($project->data, 'resources', ''));
    $savings = (float) data_get($project->data, 'token_consumption.savings_percent', 57);
    $aiUsage = $budgetService->normalizeAiUsagePercent(
        data_get($project->data, 'ai_usage_percent', \App\Services\ProjectBudgetSpecService::DEFAULT_AI_USAGE_PERCENT)
    );
    $budgetLogoPath = (string) config('variables.logo.budget_path', 'assets/idoneo-logo.svg');
    $logoUrl = asset($budgetLogoPath);
    $isoUrl = \App\Helpers\Helpers::logoAsset('iso');

    $formatHoursHuman = function ($hours): string {
        if (! is_numeric($hours) || (float) $hours <= 0) {
            return '—';
        }
        $totalMinutes = (int) round(((float) $hours) * 60);
        $wholeHours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        if ($wholeHours > 0 && $minutes > 0) {
            return $wholeHours.' h '.$minutes.' min';
        }
        if ($wholeHours > 0) {
            return $wholeHours.' h';
        }

        return $minutes.' min';
    };

    $formatEuros = function ($amount): string {
        if (! is_numeric($amount)) {
            return '—';
        }

        return number_format((float) $amount, 2, ',', '.').' €';
    };

    $rows = [];
    $totalLabor = 0.0;
    $totalTokenBillable = 0.0;
    $totalTokenCost = 0.0;
    $totalHours = 0.0;
    $totalHoursSaved = 0.0;
    $totalDisplayTokens = 0;
    $moneySaved = 0.0;

    foreach ($suggestedTasks as $t) {
        if (! is_array($t) || (($t['included'] ?? true) === false)) {
            continue;
        }
        $hours = isset($t['estimated_hours']) && is_numeric($t['estimated_hours']) ? (float) $t['estimated_hours'] : 0.0;
        $level = trim((string) ($t['resource_level'] ?? '')) ?: '—';
        $price = isset($t['unit_price']) && $t['unit_price'] !== '' && $t['unit_price'] !== null ? (float) $t['unit_price'] : null;
        $baseTokens = $budgetService->resolveEstimatedTokens($t);
        $balanced = $budgetService->applyHoursTokensBalance($price, $hours, $baseTokens, $savings, $aiUsage);
        $laborCharged = $balanced['labor'];
        $hoursCharged = $balanced['hours'];
        $billable = $balanced['token_billable'];
        $displayTokens = $balanced['display_tokens'];
        $cost = $balanced['cost'];
        $baseRemaining = max(0.01, 1 - ($savings / 100));
        $baseCost = $budgetService->estimateTokenCostEuros((int) round($baseTokens * 0.7), max(0, $baseTokens - (int) round($baseTokens * 0.7)));
        $baseBillable = round($baseCost / $baseRemaining, 2);
        $baseDisplayTokens = (int) round($baseTokens / $baseRemaining);

        if ($laborCharged !== null) {
            $totalLabor += $laborCharged;
        }
        $totalTokenBillable += $billable;
        $totalTokenCost += $cost;
        $totalHours += $hoursCharged;
        $totalHoursSaved += max(0, ($baseDisplayTokens - $baseTokens) / 20000);
        $totalDisplayTokens += $displayTokens;
        $moneySaved += max(0, $baseBillable - $baseCost);

        $rows[] = [
            'title' => (string) ($t['title'] ?? '—'),
            'hours' => $formatHoursHuman($hoursCharged),
            'level' => $level,
            'labor' => $laborCharged !== null ? $formatEuros($laborCharged) : '—',
            'tokens' => ($displayTokens > 0 || $billable > 0) ? $budgetService->formatTokenCount($displayTokens) : '—',
            'token_cost' => ($displayTokens > 0 || $billable > 0) ? $formatEuros($billable) : '—',
        ];
    }

    $grandTotal = (int) round($totalLabor + $totalTokenBillable);
    $weeks = $totalHours > 0 ? (int) ceil($totalHours / 40) : 0;
    $hasContent = $dimension !== '' || $estimatedTimes !== '' || $resources !== '' || count($rows) > 0;
    $discountPercent = is_numeric($project->discount) ? max(0.0, min(100.0, (float) $project->discount)) : 0.0;
    $discountedTotal = (int) round($grandTotal * (1 - ($discountPercent / 100)));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Budget preview') }}{{ $projectName !== '' ? ' — '.$projectName : '' }}</title>
    <link rel="icon" href="{{ $isoUrl }}" type="image/svg+xml">
    <style>
        @page { margin: 2.2cm 2cm; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Helvetica Neue", Arial, sans-serif;
            color: #1f2937;
            font-size: 11.5px;
            line-height: 1.6;
            max-width: 820px;
            margin: 0 auto;
            padding: 28px 20px 48px;
            background: #f3f4f6;
        }
        .sheet {
            background: #fff;
            padding: 28px 32px 36px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .report-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 3px solid #4361f7;
            padding-bottom: 10px;
        }
        .report-header img { height: 34px; flex-shrink: 0; }
        h1 { font-size: 21px; color: #111827; margin: 0; font-weight: 700; }
        .subtitle { font-size: 14px; color: #4b5563; margin: 8px 0 18px; font-weight: 600; }
        .meta { color: #4b5563; margin: 3px 0; }
        .meta strong { color: #111827; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }
        h2 { font-size: 14px; color: #111827; margin: 24px 0 8px; font-weight: 700; }
        .prose { color: #374151; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0 16px; }
        th, td { text-align: left; padding: 7px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.total td {
            font-weight: 700;
            color: #111827;
            border-top: 2px solid #4361f7;
            border-bottom: none;
            background: #f8faff;
        }
        .highlight {
            background: #f8faff;
            border-left: 3px solid #4361f7;
            padding: 10px 14px;
            margin: 14px 0;
        }
        .footer {
            margin-top: 32px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 9.5px;
        }
        .empty { color: #6b7280; margin: 24px 0; }
        @media print {
            body { background: #fff; padding: 0; max-width: none; }
            .sheet { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="report-header">
        <h1>{{ __('Summary of requested quote and values') }}</h1>
        <img src="{{ $logoUrl }}" alt="IDONEO">
    </div>

    @if ($projectName !== '')
        <p class="subtitle">{{ $projectName }}</p>
    @endif

    @if ($clientName !== '')
        <p class="meta"><strong>{{ __('Client') }}:</strong> {{ $clientName }}</p>
    @endif
    <p class="meta"><strong>{{ __('Report date') }}:</strong> {{ now()->format('d/m/Y') }}</p>

    <hr>

    @unless ($hasContent)
        <p class="empty">{{ __('No budget breakdown available.') }}</p>
    @else
        @if ($dimension !== '')
            <h2>{{ __('Dimension') }}</h2>
            @foreach (preg_split("/\r\n|\n|\r/", $dimension) ?: [] as $line)
                @if (trim($line) !== '')
                    <p class="prose">{{ trim($line) }}</p>
                @endif
            @endforeach
        @endif

        @if ($estimatedTimes !== '')
            <h2>{{ __('Estimated times') }}</h2>
            @foreach (preg_split("/\r\n|\n|\r/", $estimatedTimes) ?: [] as $line)
                @if (trim($line) !== '')
                    <p class="prose">{{ trim($line) }}</p>
                @endif
            @endforeach
        @endif

        @if ($resources !== '')
            <h2>{{ __('Resources') }}</h2>
            @foreach (preg_split("/\r\n|\n|\r/", $resources) ?: [] as $line)
                @if (trim($line) !== '')
                    <p class="prose">{{ trim($line) }}</p>
                @endif
            @endforeach
        @endif

        @if (count($rows) > 0)
            <h2>{{ __('Details') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Description') }}</th>
                        <th class="num">{{ __('Hours') }}</th>
                        <th class="num">{{ __('Level') }}</th>
                        <th class="num">{{ __('labor') }}</th>
                        <th class="num">{{ __('Tokens') }}</th>
                        <th class="num">{{ __('Tokens') }} €</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['title'] }}</td>
                            <td class="num">{{ $row['hours'] }}</td>
                            <td class="num">{{ $row['level'] }}</td>
                            <td class="num">{{ $row['labor'] }}</td>
                            <td class="num">{{ $row['tokens'] }}</td>
                            <td class="num">{{ $row['token_cost'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="3">{{ __('Total') }}</td>
                        <td class="num">{{ $formatEuros($totalLabor) }}</td>
                        <td class="num">{{ $totalDisplayTokens > 0 ? $budgetService->formatTokenCount($totalDisplayTokens) : '—' }}</td>
                        <td class="num">{{ $formatEuros($totalTokenBillable) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="highlight">
                @if ($discountPercent > 0)
                    <strong>{{ __('Total') }}:
                        <s>{{ number_format($grandTotal, 0, '', '.') }}€</s>
                        {{ number_format($discountedTotal, 0, '', '.') }}€ + {{ __('I.V.A.') }}
                    </strong><br>
                    {{ __('Discount') }}: {{ rtrim(rtrim(number_format($discountPercent, 1, ',', ''), '0'), ',') }}%
                    · {{ __('labor') }} {{ $formatEuros($totalLabor) }}
                    · {{ __('Tokens') }} {{ $formatEuros($totalTokenBillable) }}
                @else
                    <strong>{{ __('Total') }}: {{ number_format($grandTotal, 0, '', '.') }}€ + {{ __('I.V.A.') }}</strong><br>
                    {{ __('labor') }} {{ $formatEuros($totalLabor) }}
                    · {{ __('Tokens') }} {{ $formatEuros($totalTokenBillable) }}
                @endif
            </div>

            @if ($weeks > 0)
                <p class="prose">{{ __('Estimated development time, :weeks weeks after the budget has been confirmed.', ['weeks' => $weeks]) }}</p>
            @endif
        @endif
    @endunless

    <p class="footer">
        @if ($moneySaved > 0 || $totalHoursSaved > 0)
            {{ __('With our MCP you save :money and about :time.', [
                'money' => $formatEuros($moneySaved),
                'time' => $formatHoursHuman($totalHoursSaved),
            ]) }}
            ·
        @endif
        {{ __('Internal draft — not issued in Stripe. Billable reflects cost without MCP/TOON optimization; savings % is the transferred margin.') }}
    </p>
</div>
</body>
</html>
