<?php

return [
    'sync_button' => 'Sync invoices',
    'sync_success' => 'Invoices synced from :providers. :imported new, :updated updated.',
    'sync_warning_skipped' => 'Synced from :providers but :skipped invoice(s) could not be imported (missing counterparty data).',
    'errors' => [
        'no_team' => 'No team selected.',
        'nothing_configured' => 'Configure Stripe or Cuéntica in team settings to sync invoices.',
    ],
    'providers' => [
        'stripe' => 'Stripe',
        'cuentica' => 'Cuéntica',
    ],
];
