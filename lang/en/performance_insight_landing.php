<?php

return [
    'page_title' => 'Daily Performance Insight · Your team priority every morning',
    'meta_description' => 'Humano summarizes WhatsApp, email, tasks, invoices and appointments in a daily AI briefing. Act with suggested replies and scheduled sends.',

    'nav' => [
        'metrics' => 'Metrics',
        'product' => 'Product',
        'features' => 'Features',
        'guide' => 'Guide',
        'cta' => 'Get started',
        'login' => 'Log in',
    ],

    'hero' => [
        'eyebrow' => 'Performance Insight · AI + operations',
        'title' => 'Know what matters today before your first coffee',
        'lead' => 'Every morning you get a headline, a five-word focus and an actionable message. Unread WhatsApp, overdue invoices, today\'s tasks and appointments — all in one insight.',
        'cta_secondary' => 'Interactive guide',
        'cta_newsletter' => 'Newsletter piece',
    ],

    'metrics' => [
        'eyebrow' => 'Team data',
        'title' => 'Metrics that power the insight',
        'lead' => 'Humano cross-references signals from active modules and calculates a performance ratio (0–100) to guide the day\'s focus.',
        'ratio_label' => 'Performance ratio',
        'ratio_value' => '78',
        'chart_title' => 'Activity last 7 days',
        'chart_days' => ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
        'highlights_title' => 'Monitored signals',
        'highlights' => [
            ['label' => 'Unread WhatsApp', 'value' => '12', 'pct' => 85],
            ['label' => 'Pending emails', 'value' => '8', 'pct' => 62],
            ['label' => 'Overdue tasks', 'value' => '3', 'pct' => 40],
            ['label' => 'Unpaid invoices', 'value' => '5', 'pct' => 55],
            ['label' => 'Appointments today', 'value' => '4', 'pct' => 30],
        ],
    ],

    'dashboard' => [
        'eyebrow' => 'Dashboard',
        'title' => 'Insight card on the dashboard',
        'lead' => 'Admins open Humano and see the daily briefing: headline, focus, message and key summary points.',
        'headline' => '⚡ Focus',
        'focus' => 'Collect overdue invoices today',
        'message' => 'You have 5 unpaid invoices and 3 overdue tasks. Prioritize IDONEO follow-up and reply to billing WhatsApp before noon.',
        'highlights' => [
            '5 invoices with outstanding balance',
            '8 unread emails in mailbox',
            '4 appointments scheduled today',
        ],
    ],

    'notification' => [
        'eyebrow' => 'Action',
        'title' => 'Suggested replies and scheduled send',
        'lead' => 'From the notification, expand each highlight, copy the suggested reply or schedule email/WhatsApp send in 2 hours.',
        'preview_from' => 'contabilidad@idoneo.es',
        'preview_subject' => 'Re: Pending invoice — IDONEO',
        'preview_body' => 'Good morning, please find the pending invoice details…',
        'suggestion' => 'Hi Laura. I am reaching out about overdue invoice F-IDO-2026-01 (€4,820.00). Can we coordinate payment this week?',
        'schedule_email' => 'Schedule email (2 h)',
        'scheduled_badge' => 'Scheduled for 19/06/2026 12:44',
        'cancel' => 'Unschedule',
    ],

    'channels' => [
        'eyebrow' => 'Channels',
        'title' => 'Email, bell and dashboard',
        'lead' => 'The same insight arrives via Markdown email, in-app notification and dashboard card. Filterable 60-day history.',
        'email' => 'Morning email',
        'bell' => 'In-app notification',
        'card' => 'Dashboard card',
        'history' => '60-day history',
    ],

    'features' => [
        'eyebrow' => 'Included',
        'title' => 'Briefing ready to act on',
        'items' => [
            [
                'title' => 'AI + fallback templates',
                'text' => 'Headline, focus and message via Anthropic; emergency Spanish templates if AI fails.',
            ],
            [
                'title' => 'Invoice context',
                'text' => 'Detects outstanding balances and suggests replies with invoice number and amount.',
            ],
            [
                'title' => 'Schedule in 2 hours',
                'text' => 'Send the suggested reply later without leaving the notification; cancel if priorities change.',
            ],
            [
                'title' => '/generate-insight command',
                'text' => 'Regenerate the insight manually from web assistant or WhatsApp when needed.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Ready to start the day with clarity?',
        'lead' => 'Enable the Performance Insights module on your team and receive the first briefing tomorrow at 06:15.',
        'button' => 'I want the daily insight',
        'secondary' => 'View presentation',
    ],

    'lead' => [
        'sources' => [
            'hero' => 'Performance Insight landing · hero',
            'cta' => 'Performance Insight landing · CTA',
        ],
    ],

    'newsletter' => [
        'page_title' => 'Newsletter · Humano Performance Insight',
        'preview_note' => 'Campaign preview. Copy the HTML or use as a template reference.',
        'subject' => 'New: your daily operations briefing in Humano',
        'preheader' => 'Headline, focus and concrete actions every morning — WhatsApp, email, tasks and invoices.',
        'headline' => 'Start the day knowing what matters',
        'intro' => 'Performance Insight summarizes your team operations in one clear message: what to review, who to reply to and what to collect. With suggested replies and 2-hour scheduled send.',
        'admin_title' => 'For you (admin)',
        'admin_bullets' => [
            'Performance ratio and daily highlight summary',
            'Email + notification + dashboard card',
            'Suggested replies with invoice and appointment context',
        ],
        'user_title' => 'Signals it tracks',
        'user_bullets' => [
            'Unread WhatsApp and email',
            'Overdue tasks and today\'s appointments',
            'Unpaid invoices and stressed clients',
        ],
        'cta' => 'Discover Daily Insight',
        'cta_guide' => 'View guide',
        'footer' => 'Humano · CRM, operations and performance in one place.',
        'badge' => 'Performance Insight',
        'ratio_label' => 'Ratio today',
        'ratio_value' => '78/100',
        'focus_label' => 'Today\'s focus',
        'focus_value' => 'Collect overdue invoices today',
    ],
];
