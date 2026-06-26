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
];
