<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('affiliate_commission_receipt.page_title', ['receipt' => $receiptNumber]) }}</title>
    <style>
        body { margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.45; }
        .wrap { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .pad { padding: 28px 24px 24px; }
        .logo-row { text-align: center; margin-bottom: 20px; }
        .logo-row img { height: 40px; width: auto; }
        h1 { margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; }
        .intro { margin: 0 0 20px; font-size: 15px; color: #334155; text-align: center; }
        .receipt-box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 0 0 20px; background: #fff; }
        .row { display: flex; justify-content: space-between; gap: 12px; margin: 0 0 10px; font-size: 14px; }
        .row .label { color: #64748b; }
        .row .value { color: #0f172a; font-weight: 600; text-align: right; }
        .total { border-top: 1px solid #e2e8f0; margin-top: 14px; padding-top: 14px; }
        .total .value { color: #696cff; font-size: 18px; }
        .muted { font-size: 13px; color: #64748b; text-align: center; margin: 16px 0 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pad">
            <div class="logo-row">
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" height="40" style="height: 40px; width: auto;">
            </div>

            <h1>{{ __('affiliate_commission_receipt.heading') }}</h1>
            <p class="intro">{{ __('affiliate_commission_receipt.intro', ['team' => $referrerTeamName]) }}</p>

            <div class="receipt-box">
                <div class="row">
                    <span class="label">{{ __('affiliate_commission_receipt.receipt_number') }}</span>
                    <span class="value">{{ $receiptNumber }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('affiliate_commission_receipt.date') }}</span>
                    <span class="value">{{ $recordedAt }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('affiliate_commission_receipt.referred_client') }}</span>
                    <span class="value">{{ $payingTeamName }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('affiliate_commission_receipt.service') }}</span>
                    <span class="value">{{ $serviceLabel }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('affiliate_commission_receipt.payment_amount') }}</span>
                    <span class="value">{{ $amountPaid }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ __('affiliate_commission_receipt.commission_rate') }}</span>
                    <span class="value">{{ $commissionPercent }}%</span>
                </div>
                <div class="row total">
                    <span class="label">{{ __('affiliate_commission_receipt.commission_amount') }}</span>
                    <span class="value">{{ $commissionAmount }}</span>
                </div>
            </div>

            <p class="muted">{{ __('affiliate_commission_receipt.footer', ['invoice' => $stripeInvoiceId]) }}</p>
        </div>
    </div>
</body>
</html>
