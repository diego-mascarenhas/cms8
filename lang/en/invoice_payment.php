<?php

return [
    'register_title' => 'Record payment',
    'amount' => 'Amount',
    'date' => 'Date',
    'account' => 'Account',
    'type' => 'Payment method',
    'remarks' => 'Remarks',
    'submit' => 'Record payment',
    'no_accounts' => 'There are no active accounts in :currency to record this payment.',
    'success' => 'The payment was recorded successfully.',
    'errors' => [
        'not_allowed' => 'You cannot record payments for this invoice.',
        'amount_invalid' => 'The amount must be greater than zero.',
        'amount_exceeds_balance' => 'The amount cannot exceed the outstanding balance.',
        'account_invalid' => 'The selected account is not valid.',
        'account_currency_mismatch' => 'The account must use the same currency as the invoice.',
    ],
];
