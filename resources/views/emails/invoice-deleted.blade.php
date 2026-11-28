<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invoice deleted') }}</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.5; }
        .wrap { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .pad { padding: 28px 24px 24px; }
        h1 { margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; }
        p { margin: 0 0 14px; font-size: 15px; color: #334155; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0; background: #f8fafc; }
        .row { display: flex; justify-content: space-between; gap: 12px; margin: 0 0 8px; font-size: 14px; }
        .row .label { color: #64748b; }
        .row .value { color: #0f172a; font-weight: 600; text-align: right; }
        .muted { font-size: 13px; color: #64748b; text-align: center; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            <h1>{{ __('Invoice deleted') }}</h1>
            <p>{{ __('An administrator deleted an invoice from the :team team.', ['team' => $teamName]) }}</p>

            <div class="box">
                <div class="row">
                    <span class="label">{{ __('Invoice number') }}</span>
                    <span class="value">{{ $invoiceNumber }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('Enterprise') }}</span>
                    <span class="value">{{ $enterpriseName }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('Amount') }}</span>
                    <span class="value">{{ $amountLabel }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('Operation') }}</span>
                    <span class="value">{{ $operationLabel }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('Deleted by') }}</span>
                    <span class="value">{{ $deletedByName }} ({{ $deletedByEmail }})</span>
                </div>
            </div>

            <p class="muted">{{ __('If you did not expect this change, review the invoices list in Humano.') }}</p>
        </div>
    </div>
</body>
</html>
