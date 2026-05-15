<?php

return [
    'page_title' => 'Help — SPF and DNS for outgoing email',
    'title' => 'Help — SPF and DNS for outgoing email',
    'intro' => 'When your team uses the platform’s outgoing email (system SMTP), Humano checks the domain of your “From” address. Your apex SPF TXT must include Revision Alpha’s SPF include (you may keep other mechanisms such as MX, IP4, or other includes).',

    'required_record_heading' => 'Required mechanism in SPF',
    'required_record_body' => 'On the root of your sending domain (the part after @ in the team From address), your SPF TXT must contain this include (case-insensitive). You can add it alongside your existing mechanisms:',

    'example_heading' => 'Minimal example',
    'example_body' => 'If you only send through this platform for that domain, a minimal record is:',

    'domain_heading' => 'Which domain?',
    'domain_body' => 'The check uses the domain of the outgoing From address configured for your team (Team settings → email / notifications). If you send as noreply@example.com, SPF is validated on example.com.',

    'why_heading' => 'Why this include?',
    'why_body' => 'It authorizes Revision Alpha’s sending infrastructure to send on behalf of your domain when using system SMTP.',

    'includes_chain_heading' => 'Nested includes',
    'includes_chain_body' => 'If your SPF uses include:another-domain and that record eventually includes spf.revisionalpha.com, Humano follows a short chain of includes (up to 5 levels) when resolving TXT.',

    'propagation_heading' => 'DNS propagation',
    'propagation_body' => 'After saving at your DNS provider, global resolvers can take from minutes to 48 hours. Humano reads DNS from the application server; tools like MXToolbox may show the record before your server sees it, or the opposite, depending on resolver cache.',

    'verify_heading' => 'How to verify',
    'verify_body' => 'Use an external SPF/DNS checker for your apex domain, or query TXT from a terminal (example):',
    'verify_note' => 'Look for a TXT line starting with v=spf1 that contains include:spf.revisionalpha.com.',

    'own_smtp_heading' => 'Using your own SMTP',
    'own_smtp_body' => 'If your team configures custom SMTP (host, user, etc.) in team settings, these SPF checks for “system SMTP” do not apply to sending through your own server—your provider’s requirements still apply.',

    'back_to_help' => 'Back to Help home',
];
