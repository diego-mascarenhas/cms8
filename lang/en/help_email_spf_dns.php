<?php

return [
    'page_title' => 'Help — SPF and DNS for outgoing email',
    'title' => 'Help — SPF and DNS for outgoing email',
    'intro' => 'When your team uses the platform’s outgoing email (system SMTP), Humano checks the domain of your “From” address. You must publish one exact SPF TXT record on that domain’s apex.',

    'required_record_heading' => 'Required TXT (SPF) value',
    'required_record_body' => 'Create or update a TXT record on the root of your sending domain (the part after @ in the team From address). The value must match exactly (only spaces and letter case may differ):',

    'domain_heading' => 'Which domain?',
    'domain_body' => 'The check uses the domain of the outgoing From address configured for your team (Team settings → email / notifications). If you send as noreply@example.com, SPF is validated on example.com.',

    'why_heading' => 'Why an exact record?',
    'why_body' => 'This authorizes Revision Alpha’s sending infrastructure for your domain and keeps the policy strict (-all) so receivers can trust the alignment expected by the platform.',

    'propagation_heading' => 'DNS propagation',
    'propagation_body' => 'After saving at your DNS provider, global resolvers can take from minutes to 48 hours. Humano reads DNS from the application server; tools like MXToolbox may show the record before your server sees it, or the opposite, depending on resolver cache.',

    'verify_heading' => 'How to verify',
    'verify_body' => 'Use an external SPF/DNS checker for your apex domain, or query TXT from a terminal (example):',
    'verify_note' => 'You should see one TXT line that matches the required value (after trimming spaces).',

    'own_smtp_heading' => 'Using your own SMTP',
    'own_smtp_body' => 'If your team configures custom SMTP (host, user, etc.) in team settings, these SPF checks for “system SMTP” do not apply to sending through your own server—your provider’s requirements still apply.',

    'back_to_help' => 'Back to Help home',
];
