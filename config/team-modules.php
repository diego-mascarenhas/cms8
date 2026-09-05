<?php

return [
    /*
     * |--------------------------------------------------------------------------
     * | Default Team Modules Configuration
     * |--------------------------------------------------------------------------
     * |
     * | This configuration defines which modules should be enabled by default
     * | when a new team is created or when syncing team modules.
     * |
     * | true = enabled by default
     * | false = disabled by default
     * |
     */
    'defaults' => [
        // Core modules
        'dashboard' => true,
        'users' => false,
        'settings' => true,
        'contacts' => true,
        'clients' => true,
        'list60' => true,
        'prospecting' => true,
        'services' => false,
        'projects' => false,
        'opportunities' => true,
        'tasks' => false,
        'calendar' => true,
        'notifications' => false,
        // New teams only (see EnableCoreModulesForTeam); existing teams are unchanged.
        // Not a sidebar module; insights are generated on schedule and on dashboard for admin/root.
        'performance_insights' => false,
        'insights' => false,
        'templates' => true,
        // Additional modules (billing)
        'invoices' => true,
        'payments' => false,
        'incomes' => false,
        'expenses' => false,
        'financial' => true,
        'accounting' => false,
        // Additional modules (ecommerce)
        'products' => true,
        'orders' => true,
        'stores' => true,
        // Additional modules (infrastructure)
        'servers' => false,
        'hosting' => false,
        // Additional modules (general)
        'notes' => false,
        'collaborators' => false,
        'communications' => false,
        'enterprises' => false,
        'affiliates' => false,
        'events' => false,
        'today' => false,
        'times' => false,
        'attendances' => false,
        'documentation' => false,
        'departments' => false,
        // Additional modules (campaigns)
        'campaigns' => true,
        'mailer' => true,
        'paid_ads' => false,
        // Additional modules (automation)
        'prompts' => true,
        'automations' => true,
        'funnel' => false,
        'integrations' => false,
        // Additional modules (content)
        'multimedia' => false,
        'team_files' => false,
        'cms' => false,
        'website' => false,
        'academy' => false,
        'landings' => false,
        'blog' => false,
        'ebooks' => false,
        // Additional modules (support)
        'tickets' => false,
        'mailbox' => false,
        'chat' => true,
        // Additional modules (learning)
        'languages' => false,
        'language-variants' => false,
        'fares' => false,
        'softwares' => false,
        'certifications' => false,
        'stylebooks' => false,
    ],
];
