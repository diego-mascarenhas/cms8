<?php

return [
    'link' => [
        'action_title' => 'Link to invoice',
        'title' => 'Link payment to an invoice',
        'subtitle' => 'Select the invoice this payment should be associated with.',
        'back' => 'Back to payments',
        'payment_date' => 'Payment date',
        'amount' => 'Amount',
        'enterprise' => 'Client',
        'invoice_label' => 'Invoice',
        'invoice_placeholder' => 'Select an invoice',
        'no_invoices' => 'No matching invoices with an outstanding balance were found. Create or sync the invoice first, or check the client on this payment.',
        'hint' => 'Only invoices with an outstanding balance in your team are listed. Linking will assign the invoice client to the payment when it has none yet.',
        'submit' => 'Link invoice',
        'success' => 'The payment is now linked to the invoice.',
        'errors' => [
            'already_linked' => 'This payment is already linked to an invoice.',
            'enterprise_mismatch' => 'The invoice belongs to a different client than this payment. Pick an invoice for the same client, or clear the client on the payment first.',
            'no_balance' => 'This invoice has no outstanding balance.',
            'operation_mismatch' => 'This invoice does not match the payment type (income or expense).',
        ],
    ],
];
