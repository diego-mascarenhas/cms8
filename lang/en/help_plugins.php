<?php

return [
    'sidebar_title' => 'WordPress Plugins',
    'title' => 'IDONEO WordPress Plugins',
    'intro' => 'Download the official IDONEO plugins to connect your WordPress site with Humano. Install them in your production WordPress, then configure each one as described below.',

    'download' => 'Download',
    'not_available' => 'Not available',
    'version' => 'Version',
    'size_label' => 'Size',

    'custom_fields_desc' => 'Build custom fields, repeaters, flexible content, galleries and options pages for any post type. PRO-level functionality. Field values sync with Humano automatically.',
    'cms_sync_desc' => 'Notifies Humano in real time when posts, pages or media are saved or deleted, keeping content in sync (bidirectional).',
    'chat_desc' => 'Adds a chat widget connected to your Humano assistant, using a team API token.',

    'install_title' => 'How to install',
    'install_step_download' => 'Download the plugin ZIP from the button below.',
    'install_step_upload' => 'In wp-admin go to Plugins → Add New → Upload Plugin and select the ZIP.',
    'install_step_activate' => 'Click Install Now, then Activate.',
    'order_note' => 'Recommended order: Custom Fields first, then CMS Sync, then Chat.',

    'config_title' => 'Configuration after activating',
    'cms_sync_config' => 'Go to Settings → IDONEO CMS Sync para Humano and set your Humano URL, Team ID and the Webhook secret (must match the one in Humano → Team Settings).',
    'chat_config' => 'Go to Settings → IDONEO Chat for Humano and set your Humano URL and the team API Token. Keep SSL verification enabled in production.',
    'custom_fields_config' => 'No configuration needed. Create field groups under IDONEO Fields; values sync with Humano via the REST API.',

    'production_note' => 'In production use your real Humano domain (not a .test domain) and keep SSL verification enabled.',
    'token_note' => 'Your current team API token:',
];
