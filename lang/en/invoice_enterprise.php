<?php

return [
    'no_enterprise' => 'No company',
    'link' => [
        'action_title' => 'Link to client',
        'title' => 'Link invoice to a client',
        'subtitle' => 'Select the local client (company) this invoice should belong to.',
        'back' => 'Back to invoices',
        'invoice_number' => 'Document',
        'invoice_date' => 'Date',
        'client_label' => 'Client in Humano',
        'client_placeholder' => 'Select a client',
        'no_clients' => 'No clients are available. Create a client first, or check your permissions.',
        'hint' => 'Collaborators can only link invoices to clients they are assigned to. Administrators can link to any client in the team.',
        'submit' => 'Link client',
        'success' => 'The invoice is now associated with the client.',
        'errors' => [
            'already_linked' => 'This invoice is already associated with a client.',
        ],
    ],
];
