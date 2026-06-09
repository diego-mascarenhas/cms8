<?php

return [
    'issue_title' => 'Credit note',
    'issue_button' => 'Issue in Stripe',
    'reason' => 'Reason',
    'confirm' => 'Issue the credit note in Stripe for this invoice?',
    'success' => 'Credit note :reference issued in Stripe.',
    'reasons' => [
        'duplicate' => 'Duplicate',
        'fraudulent' => 'Fraudulent',
        'order_change' => 'Order change',
        'product_unsatisfactory' => 'Product unsatisfactory',
    ],
    'errors' => [
        'not_allowed' => 'You cannot issue a credit note for this invoice.',
        'team_not_found' => 'The invoice team could not be found.',
        'stripe_not_configured' => 'Configure the team Stripe secret key in team settings.',
        'no_creditable_amount' => 'There is no amount available to issue a credit note for this invoice.',
    ],
];
