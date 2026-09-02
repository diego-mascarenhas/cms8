@php
    $budgetService = app(\App\Services\ProjectBudgetSpecService::class);
    $budgetService->applyTeamTokenPricing($project->team);
    $discriminateTokens = $budgetService->showsTokenLines();
    $includeTokens = $budgetService->includesTokenCharges();
    $projectName = trim((string) ($project->real_name ?: $project->name));
    $clientName = trim((string) (optional($project->enterprise)->name ?? ''));
    $dimension = trim((string) data_get($project->data, 'dimension', ''));
    $estimatedTimes = trim((string) data_get($project->data, 'estimated_times', ''));
    $resources = trim((string) data_get($project->data, 'resources', ''));
    $savings = (float) data_get($project->data, 'token_consumption.savings_percent', 57);
    $aiUsage = $budgetService->normalizeAiUsagePercent(
        data_get($project->data, 'ai_usage_percent', \App\Services\ProjectBudgetSpecService::DEFAULT_AI_USAGE_PERCENT)
    );
    $logoUrl = \App\Helpers\Helpers::budgetLogoAsset();
    $isoUrl = \App\Helpers\Helpers::logoAsset('iso');

    $formatHoursHuman = function ($hours): string {
        return \App\Helpers\Helpers::formatHoursHuman($hours, true);
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
    $totalHours = 0.0;
    $totalDisplayTokens = 0;

    foreach ($suggestedTasks as $t) {
        if (! is_array($t) || (($t['included'] ?? true) === false)) {
            continue;
        }
        $hours = isset($t['estimated_hours']) && is_numeric($t['estimated_hours']) ? (float) $t['estimated_hours'] : 0.0;
        $level = trim((string) ($t['resource_level'] ?? '')) ?: '—';
        $price = isset($t['unit_price']) && $t['unit_price'] !== '' && $t['unit_price'] !== null ? (float) $t['unit_price'] : null;
        $baseTokens = $budgetService->resolveEstimatedTokens($t);
        $balanced = $budgetService->applyHoursTokensBalance($price, $hours, $baseTokens, $savings, $aiUsage);
        $rounded = $budgetService->roundLaborToHalfHourSteps($balanced['labor'], $balanced['hours']);
        $laborCharged = $rounded['labor'];
        $hoursCharged = $rounded['hours'];
        $billable = $balanced['token_billable'];
        $displayTokens = $balanced['display_tokens'];

        if ($laborCharged !== null) {
            $totalLabor += $laborCharged;
        }
        $chargedTokens = $includeTokens ? $billable : 0.0;
        $totalTokenBillable += $chargedTokens;
        $totalHours += $hoursCharged;
        $totalDisplayTokens += $includeTokens ? $displayTokens : 0;

        $laborDisplay = $laborCharged ?? 0.0;
        if ($includeTokens && ! $discriminateTokens)
        {
            $laborDisplay += $chargedTokens;
        }

        $rows[] = [
            'title' => (string) ($t['title'] ?? '—'),
            'hours' => $formatHoursHuman($hoursCharged),
            'level' => $level,
            'labor' => ($laborCharged !== null || $chargedTokens > 0) ? $formatEuros($laborDisplay) : '—',
            'tokens' => ($displayTokens > 0 || $chargedTokens > 0) ? $budgetService->formatTokenCount($displayTokens) : '—',
            'token_cost' => ($displayTokens > 0 || $chargedTokens > 0) ? $formatEuros($chargedTokens) : '—',
        ];
    }

    $grandTotal = (int) round($totalLabor + $totalTokenBillable);
    $weeks = $totalHours > 0 ? (int) ceil($totalHours / 40) : 0;
    $hasContent = $dimension !== '' || $estimatedTimes !== '' || $resources !== '' || count($rows) > 0;
    $discountPercent = is_numeric($project->discount) ? max(0.0, min(100.0, (float) $project->discount)) : 0.0;
    $laborDiscountAmount = round($totalLabor * ($discountPercent / 100), 2);
    $discountedLabor = round($totalLabor - $laborDiscountAmount, 2);
    $discountedTotal = (int) round($discountedLabor + $totalTokenBillable);
    $payableTotal = $discountPercent > 0 ? $discountedTotal : $grandTotal;
    $depositAmount = (int) round($payableTotal * 0.30);
    $clientResponse = is_array($clientResponse ?? null) ? $clientResponse : null;
    $responseStatus = is_array($clientResponse) ? ($clientResponse['status'] ?? null) : null;
    $budgetToken = $budgetToken ?? data_get($project->data, 'budget_preview_token');
    $quoteIsClosed = $project->isBudgetApproved()
        || in_array($responseStatus, ['accepted', 'reformulation_requested'], true);
    $quoteIsAccepted = $project->isBudgetApproved() || $responseStatus === 'accepted';
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
        .notice {
            background: #fff7ed;
            border-left: 3px solid #ea580c;
            padding: 12px 14px;
            margin: 18px 0 12px;
            color: #9a3412;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 6px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary { background: #4361f7; color: #fff; }
        .btn-primary:hover { background: #3451e0; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-secondary:hover { background: #d1d5db; }
        .field { margin: 14px 0; }
        .field label { display: block; font-weight: 600; margin-bottom: 6px; color: #111827; font-size: 13px; }
        .field input, .field textarea, .accept-form input[type="text"] {
            width: 100%;
            border: 1px solid #d9dee3;
            border-radius: 6px;
            padding: 10px 14px;
            font: inherit;
            font-size: 14px;
            line-height: 1.5;
            color: #697a8d;
            background: #fff;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .field input:focus, .field textarea:focus, .accept-form input[type="text"]:focus {
            outline: 0;
            border-color: #4361f7;
            box-shadow: 0 0 0 .2rem rgba(67, 97, 247, .16);
            color: #697a8d;
        }
        .field textarea { min-height: 120px; resize: vertical; }
        .check { display: flex; gap: 10px; align-items: flex-start; margin: 14px 0; color: #374151; font-size: 14px; }
        .check input {
            width: 1.05rem;
            height: 1.05rem;
            margin-top: 3px;
            flex-shrink: 0;
            accent-color: #4361f7;
        }
        .accept-form {
            margin-top: 8px;
            padding: 16px 18px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fafbfc;
        }
        .check.is-invalid { color: #991b1b; }
        .is-invalid {
            border-color: #dc2626 !important;
        }
        .invalid-feedback {
            display: block;
            color: #dc2626;
            font-size: 12px;
            margin: 4px 0 8px;
        }
        .flash-ok {
            background: #ecfdf5;
            border-left: 3px solid #059669;
            color: #065f46;
            padding: 10px 14px;
            margin: 14px 0;
        }
        .flash-err {
            background: #fef2f2;
            border-left: 3px solid #dc2626;
            color: #991b1b;
            padding: 10px 14px;
            margin: 14px 0;
        }
        .status-box {
            background: #f3f4f6;
            border-left: 3px solid #6b7280;
            padding: 10px 14px;
            margin: 14px 0;
        }
        dialog {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            max-width: 520px;
            width: calc(100% - 32px);
        }
        dialog::backdrop { background: rgba(17, 24, 39, 0.45); }
        .dialog-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 14px; }
        @media print {
            body { background: #fff; padding: 0; max-width: none; }
            .sheet { box-shadow: none; padding: 0; }
            .actions, .notice, dialog, .flash-ok, .flash-err { display: none !important; }
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
                        @if ($discriminateTokens)
                            <th class="num">{{ __('Tokens') }}</th>
                            <th class="num">{{ __('Tokens') }} €</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['title'] }}</td>
                            <td class="num">{{ $row['hours'] }}</td>
                            <td class="num">{{ $row['level'] }}</td>
                            <td class="num">{{ $row['labor'] }}</td>
                            @if ($discriminateTokens)
                                <td class="num">{{ $row['tokens'] }}</td>
                                <td class="num">{{ $row['token_cost'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="3">{{ __('Total') }}</td>
                        <td class="num">{{ $formatEuros($discriminateTokens || ! $includeTokens ? $totalLabor : ($totalLabor + $totalTokenBillable)) }}</td>
                        @if ($discriminateTokens)
                            <td class="num">{{ $totalDisplayTokens > 0 ? $budgetService->formatTokenCount($totalDisplayTokens) : '—' }}</td>
                            <td class="num">{{ $formatEuros($totalTokenBillable) }}</td>
                        @endif
                    </tr>
                </tbody>
            </table>

            <div class="highlight">
                <div style="display:grid;grid-template-columns:1fr auto;gap:4px 16px;max-width:320px;">
                    <span>{{ __('Labor') }}</span>
                    <span style="text-align:right;">{{ $formatEuros($discriminateTokens || ! $includeTokens ? $totalLabor : ($totalLabor + $totalTokenBillable)) }}</span>
                    @if ($discriminateTokens)
                        <span>{{ __('Tokens') }}</span>
                        <span style="text-align:right;">{{ $formatEuros($totalTokenBillable) }}</span>
                    @endif
                    <span>{{ __('Subtotal') }}</span>
                    <span style="text-align:right;">{{ $formatEuros($grandTotal) }}</span>
                    @if ($discountPercent > 0)
                        <span>{{ __('Discount on labor') }} (−{{ rtrim(rtrim(number_format($discountPercent, 1, ',', ''), '0'), ',') }}%)</span>
                        <span style="text-align:right;">−{{ $formatEuros($laborDiscountAmount) }}</span>
                    @endif
                    <strong>{{ __('Total') }}</strong>
                    <strong style="text-align:right;">{{ $formatEuros($payableTotal) }} + {{ __('I.V.A.') }}</strong>
                </div>
            </div>

            @if ($weeks > 0)
                <p class="prose">{{ __('Estimated development time, :weeks weeks after the budget has been confirmed.', ['weeks' => $weeks]) }}</p>
            @endif
        @endif
    @endunless

    @if (session('budget_response_success'))
        <div class="flash-ok">{{ session('budget_response_success') }}</div>
    @endif
    @if (session('budget_response_error'))
        <div class="flash-err">{{ session('budget_response_error') }}</div>
    @endif

    @if ($hasContent && $budgetToken)
        @if ($quoteIsAccepted)
            <div class="status-box">
                <strong>{{ __('Quote accepted') }}</strong><br>
                {{ __('The project will not start until 30% of the payment is received.') }}
                @if (! empty($clientResponse['responded_at']))
                    <br><span style="color:#6b7280;">{{ __('Recorded on') }}: {{ \Carbon\Carbon::parse($clientResponse['responded_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                @endif
            </div>
        @elseif ($responseStatus === 'reformulation_requested')
            <div class="status-box">
                <strong>{{ __('Reformulation requested') }}</strong><br>
                {{ __('We received your comments and will get back to you.') }}
                @if (! empty($clientResponse['message']))
                    <br><em style="display:block;margin-top:6px;">{{ $clientResponse['message'] }}</em>
                @endif
            </div>
        @elseif (! $quoteIsClosed)
            <div class="notice">
                <strong>{{ __('Important before accepting') }}</strong><br>
                {{ __('The project will not start until 30% of the payment is received (:amount).', [
                    'amount' => $formatEuros($depositAmount),
                ]) }}
                <br>
                {{ __('The remaining amount (:remaining): if you authorize the debit, it will be charged when the project is completed; if you pay yourself, payment is due upon completion or within 30 days after the agreed completion date.', [
                    'remaining' => $formatEuros(max(0, $payableTotal - $depositAmount)),
                ]) }}
            </div>

            <form method="POST" action="{{ route('project.budget-preview.accept', $budgetToken) }}" class="accept-form" novalidate>
                @csrf
                <div class="field">
                    <label for="accepted_by_name">{{ __('Your name') }} ({{ __('optional') }})</label>
                    <input type="text" id="accepted_by_name" name="accepted_by_name" value="{{ old('accepted_by_name') }}" maxlength="255" class="@error('accepted_by_name') is-invalid @enderror" placeholder="{{ __('Your name') }}">
                    @error('accepted_by_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <label class="check @error('accept_debit') is-invalid @enderror">
                    <input type="checkbox" name="accept_debit" value="1" {{ old('accept_debit') ? 'checked' : '' }}>
                    <span>{{ __('I authorize the debit of this amount (:amount).', ['amount' => $formatEuros($depositAmount)]) }}</span>
                </label>
                @error('accept_debit')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="actions">
                    <button type="submit" class="btn btn-primary">{{ __('Accept quote') }}</button>
                    <button type="button" class="btn btn-secondary" id="open-reformulate-dialog">{{ __('Request reformulation') }}</button>
                </div>
            </form>

            <dialog id="reformulate-dialog">
                <form method="POST" action="{{ route('project.budget-preview.reformulate', $budgetToken) }}" novalidate>
                    @csrf
                    <h2 style="margin:0 0 8px;font-size:16px;">{{ __('Request reformulation') }}</h2>
                    <p style="margin:0 0 12px;color:#4b5563;">{{ __('Tell us what you would like to change in this quote.') }}</p>
                    <div class="field">
                        <label for="reformulate_name">{{ __('Your name') }} ({{ __('optional') }})</label>
                        <input type="text" id="reformulate_name" name="name" value="{{ old('name') }}" maxlength="255" class="@error('name') is-invalid @enderror" placeholder="{{ __('Your name') }}">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="reformulate_message">{{ __('Comments') }} (*)</label>
                        <textarea id="reformulate_message" name="message" maxlength="5000" class="@error('message') is-invalid @enderror" placeholder="{{ __('Comments') }}">{{ old('message') }}</textarea>
                        @error('message')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="dialog-actions">
                        <button type="button" class="btn btn-secondary" id="close-reformulate-dialog">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Send request') }}</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endif
</div>
@if ($hasContent && $budgetToken && ! $quoteIsClosed)
<script>
    (function () {
        var dialog = document.getElementById('reformulate-dialog');
        var openBtn = document.getElementById('open-reformulate-dialog');
        var closeBtn = document.getElementById('close-reformulate-dialog');
        if (!dialog || !openBtn) return;
        openBtn.addEventListener('click', function () {
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                dialog.close();
            });
        }
        @if ($errors->has('message') || $errors->has('name'))
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        @endif
    })();
</script>
@endif
</body>
</html>
