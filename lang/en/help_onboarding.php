<?php

return [
    'page_title' => 'Onboarding — First steps after payment',
    'title' => 'Onboarding: from subscription to connected WhatsApp',
    'lead' => 'This guide covers the three essential steps after subscribing to Humano: pay for your plan, configure your business, and link WhatsApp. It is the journey you will see on the dashboard right after sign-in.',
    'video_note' => 'This documentation is intended as a script for onboarding video tutorials (checkout, business setup, and QR scan).',

    'overview_heading' => 'Journey overview',
    'overview_intro' => 'The usual order is below. After payment, Humano takes you to the dashboard with a yellow banner at the top so you do not miss pending steps.',
    'overview_steps' => [
        'Choose a plan and pay on the pricing page.',
        'Complete your business configuration (multi-step wizard).',
        'Scan the QR code to connect WhatsApp.',
    ],

    'dashboard_banner_heading' => 'Dashboard banner',
    'dashboard_banner_body' => 'When you open the dashboard and a step is still pending, you will see a yellow alert with quick action buttons:',
    'dashboard_banner_configure' => 'Configure business — opens the team business setup wizard.',
    'dashboard_banner_whatsapp' => 'Connect WhatsApp — opens the QR code screen.',
    'dashboard_banner_hint' => 'If business configuration is already complete but WhatsApp is still disconnected, the banner may show only the WhatsApp button.',

    'step1_heading' => '1. Checkout from Pricing',
    'step1_intro' => 'Start on the public plans page. Compare Assistant, Business, and Mentor, choose monthly or annual billing, and go to Stripe secure checkout.',
    'step1_path_label' => 'Path',
    'step1_steps' => [
        'Open your Humano site pricing page.',
        'Pick the plan that best fits your business.',
        'If available, switch between monthly and annual billing before paying.',
        'Click Subscribe (or the plan equivalent) to open Stripe checkout.',
        'Complete payment by card. If you have a promo code, enter it in checkout when Stripe shows the field.',
        'Once payment is confirmed, Humano creates or activates your workspace and redirects you to the dashboard with a welcome message.',
    ],
    'step1_after_payment' => 'If you already had an account and payment was linked to your user, sign in with the same email used at checkout. If sign-up was automatic after payment, you will be logged in when you return from checkout.',

    'step2_heading' => '2. Configure your business',
    'step2_intro' => 'This step gives the assistant your brand context: name, industry, contact details, social links, and your main business challenge. Until it is done, the yellow dashboard banner stays visible.',
    'step2_access' => 'Open it from the Configure business button on the yellow dashboard banner at the top. That button takes you straight to the setup wizard.',
    'step2_wizard_heading' => 'Wizard steps',
    'step2_wizard_steps' => [
        'Business details — name, industry, phone, WhatsApp, website, email, and description.',
        'Personal information — owner details (name, birth date, etc.).',
        'Address — location and postal code.',
        'Social networks — links to your profiles.',
        'Challenge — what you want Humano to help you solve.',
        'Review and submit — AI summary and insights report; save to finish.',
    ],
    'step2_tip' => 'Fill in at least name, industry, and a meaningful description: the assistant and WhatsApp flows use this data to reply in your brand voice.',

    'step3_heading' => '3. Scan the WhatsApp QR code',
    'step3_intro' => 'With an active plan and business setup underway, link the WhatsApp number you will use with customers. From the dashboard, click Connect WhatsApp on the yellow banner or open the onboarding screen directly.',
    'step3_path_label' => 'Path',
    'step3_phone_heading' => 'On your phone',
    'step3_phone_steps' => [
        'Open WhatsApp on the phone you will use for this business.',
        'Tap Menu (⋮) or Settings → Linked devices → Link a device.',
        'When the camera opens, point it at the QR code on the Humano screen.',
        'Confirm on your phone if WhatsApp asks you to.',
    ],
    'step3_refresh' => 'If the QR is slow to load, use Refresh QR code on the same screen. You can also open Chat — the code may refresh when you enter Chat if the connector supports it. The screen shows a note that loading may take up to 45 seconds.',
    'step3_connected' => 'When WhatsApp is linked, you will see a success message. Then go to the dashboard or open Chat to verify messages work.',
    'step3_cloud_note' => 'In some environments the flow may differ and a QR may not appear on this screen. If you do not see a QR, contact support.',

    'next_heading' => 'What is next?',
    'next_body' => 'With payment confirmed, business configured, and WhatsApp connected, you can use Chat, contacts, tasks, and the rest of your plan modules. Explore the dashboard and user manual to go deeper.',
    'next_manual_link' => 'User manual',

    'back_to_help' => '← Back to help center',

    'sidebar_title' => 'Post-payment onboarding',
    'index_card_title' => 'Onboarding: checkout, business, and WhatsApp',
    'index_card_cta' => 'Read full guide',
    'index_card_intro' => 'Step-by-step guide for after you paid and signed in: choose a plan, configure your business, and connect WhatsApp with the QR code.',
    'index_card_step_checkout' => 'Checkout at /pricing (Stripe).',
    'index_card_step_business' => 'Business setup via the Configure business button on the dashboard.',
    'index_card_step_whatsapp' => 'QR scan at /registration/onboarding/qr.',
];
