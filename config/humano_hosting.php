<?php

return [
    'default_nameservers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HOSTING_DEFAULT_NAMESERVERS', 'NS1.REVISIONALPHA.COM,NS2.REVISIONALPHA.COM')),
    ))),

    'default_spf_record' => (string) env(
        'HOSTING_DEFAULT_SPF_RECORD',
        'v=spf1 include:spf.revisionalpha.com -all',
    ),

    'flex_plan_name' => 'Flex',

    'flex_package' => (string) env('HOSTING_FLEX_PACKAGE', 'revision_flex'),

    'error_notify' => (string) env('HOSTING_ERROR_NOTIFY', 'webmaster@revisionalpha.cloud'),

    'provision_server' => (string) env('HOSTING_PROVISION_SERVER', 'odin'),

    /*
     * Markup on Porkbun wholesale domain prices when quoting and charging the client.
     * Default 30%. Raise via HOSTING_DOMAIN_MARKUP_PERCENT; floor stays at 30 for now.
     */
    'domain_markup_percent' => max(30, (float) env('HOSTING_DOMAIN_MARKUP_PERCENT', 30)),
];
