<?php

return [
    'page_title' => 'Usage rates — Help',
    'title' => 'Per-team usage billing',
    'sidebar_title' => 'Usage rates',
    'index_card_title' => 'Usage rates',
    'index_card_body' => 'Invoice preview, SCD2 rates, monthly or weekly frequency, and token breakdown by module. Root only.',
    'intro' => 'Token, WhatsApp, and mail-overage usage is billed separately from the plan quota. Each team can have its own rates and a monthly or weekly cadence. The Rates page previews what should be invoiced; Stripe does not issue those invoices yet.',

    'where_heading' => 'Where to find it',
    'where_body' => 'Only the root role sees this screen. In Account management, the euro icon on each row opens that team’s rates.',
    'where_path' => 'Accounts → euro icon → Rates',
    'where_route' => '/account-management/{id}/rates',

    'two_invoices_heading' => 'Two separate invoices',
    'two_invoices_plan' => 'Plan quota: Assistant, Business, or another subscribed product. This still appears on Stripe subscription invoices.',
    'two_invoices_usage' => 'Usage: AI tokens, WhatsApp sends, and overage emails. One usage invoice per team and period. Not issued in Stripe yet.',

    'rates_heading' => 'Rates',
    'rates_intro' => 'There are three products. If the team has no row, the platform default is used, then config.',
    'rates_tokens' => 'Token multiplier: the client sees N × real tokens at OpenRouter rates, with no extra markup. Default ×10.',
    'rates_whatsapp' => 'WhatsApp send: EUR per outbound message. Default 0.003 EUR.',
    'rates_mailer' => 'Mail send: EUR per overage email (above the plan monthly cap). Default 0.002 EUR.',
    'rates_history' => 'Saving a new rate keeps the previous one (SCD2) for usage that already happened. The page history shows From / Until / Current.',

    'frequency_heading' => 'Frequency',
    'frequency_intro' => 'Monthly or weekly, per team. With no anchor, the month runs from the 1st to the 1st and the week from Monday to Monday. Changing frequency sets that day as the anchor.',
    'frequency_weekly' => 'Weekly: 7-day windows from the change day (Wednesday to Wednesday if you change on a Wednesday).',
    'frequency_monthly' => 'Monthly: from day D to day D (the 15th to the 15th if you change on the 15th).',
    'frequency_anchor' => 'If the anchor is 29, 30, or 31 and the month is shorter, the last day of the month is used. The next month restores the anchor (31 Jan → 28/29 Feb → 31 Mar → 30 Apr → 31 May).',

    'change_heading' => 'When frequency changes',
    'change_intro' => 'Changing only amounts and keeping the same frequency saves with no prompt. Switching Monthly ↔ Weekly shows the Change billing? alert with line items and the total.',
    'change_close' => 'The open cycle closes at 00:00 on the change day. That slice becomes an adjustment invoice.',
    'change_open' => 'The change day belongs to the new cycle. The new cadence starts that day at 00:00.',
    'change_stripe' => 'Confirming closes the cycle in Humano. Nothing is issued in Stripe yet.',

    'items_heading' => 'What is printed on the invoice',
    'items_intro' => 'Each document (adjustment or open cycle) has the same three lines, even when the amount is 0.00 EUR:',
    'items_tokens' => 'AI tokens · period: billed tokens (real × multiplier) and amount.',
    'items_sources' => 'Under that, a per-module breakdown when a source exists: Chat, Projects, Insights, or others. If every token has no module, only the total is shown.',
    'items_whatsapp' => 'WhatsApp sends · period: send count and amount.',
    'items_mailer' => 'Overage emails · period: emails above the cap and amount.',
    'items_total' => 'The page preview and the confirmation modal list those items. The Tokens KPI shows the preview total (open cycle plus pending adjustments).',

    'preview_heading' => 'Preview',
    'preview_kpis' => 'Amount to bill, OpenRouter cost, markup (the multiplier gap), and total tokens.',
    'preview_table' => 'Below, one table per invoice: title, period, tokens for that slice, lines, and total. The footer is the pending total.',
    'preview_months' => 'Previous months uses calendar months, not 15–15 or Wed–Wed cycles.',

    'status_heading' => 'Current status',
    'status_not_issued' => 'Usage is calculated and shown. No Stripe usage invoice is created.',
    'status_adjustments' => 'Changing frequency stores a pending adjustment (invoiced_at empty). The preview includes it when that slice had usage.',
    'status_weeks' => 'Weeks that already closed while the team stays on weekly are not queued on their own. Usage stays in the database and will show when invoicing or when the cadence changes.',
    'status_mailer' => 'The email cap is applied to each period slice. A split month can show 0 overage even if the full month would have exceeded the cap.',

    'cli_heading' => 'Command line',
    'cli_body' => 'To set a rate without the screen (team_id 0 = platform default):',
    'cli_example' => 'php artisan billing:set-team-rate {team_id} {product} {amount}',
    'cli_products' => 'product: tokens_multiplier, whatsapp_send, or mailer_send. Optional: --from= and --currency=.',

    'related_heading' => 'Related',
    'related_stripe' => 'Stripe webhooks (plan quota and subscription invoices)',
    'related_manual' => 'Manual: customer invoices and payments (CRM), separate from this platform usage',
];
