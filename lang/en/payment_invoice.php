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
        'no_invoices' => 'No matching invoices were found. Create or sync the invoice first, or check the client on this payment.',
        'hint' => 'Only invoices in your team are listed. If the payment has a client, only that client’s invoices are shown.',
        'submit' => 'Link invoice',
        'success' => 'The payment is now linked to the invoice.',
        'errors' => [
            'already_linked' => 'This payment is already linked to an invoice.',
            'enterprise_mismatch' => 'The invoice belongs to a different client than this payment. Pick an invoice for the same client, or clear the client on the payment first.',
        ],
    ],
];
