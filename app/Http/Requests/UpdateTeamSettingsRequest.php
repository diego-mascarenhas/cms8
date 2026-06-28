<?php

namespace App\Http\Requests;

use App\Support\TeamSettingsLabels;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamSettingsRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Ajusta según tu lógica de autorización
    }

    public function rules()
    {
        return [
            // Stripe settings
            'stripe.stripe_public' => 'nullable|string|max:255',
            'stripe.stripe_secret' => 'nullable|string|max:255',
            'stripe.stripe_webhook' => 'nullable|string|max:255',

            // Fiscal export (global platform routing)
            'fiscal.fiscal_platform' => 'nullable|string|in:,cuentica,arca,none',
            'fiscal.fiscal_country' => 'nullable|string|in:,ES,AR',

            // Affiliate program (platform-wide, root only)
            'affiliates.affiliate_commission_percent' => 'nullable|numeric|min:0|max:100',

            // Cuéntica credentials (Spain)
            'cuentica.cuentica_api_token' => 'nullable|string|max:255',
            'cuentica.cuentica_invoice_serie' => 'nullable|string|max:255',
            'cuentica.cuentica_inbound_sync_enabled' => 'nullable|boolean',

            // Categories settings
            'categories.categories_default_status' => 'nullable|string|in:active,inactive',
            'categories.categories_require_approval' => 'nullable|in:0,1',
            'categories.categories_max_depth' => 'nullable|string|in:1,2,3',
            'categories.categories_allow_multiple_parents' => 'nullable|in:0,1',
            'categories.categories_default_ordering' => 'nullable|string|in:name_asc,name_desc,created_desc,created_asc,custom',

            // Notification settings
            'notifications.notifications_email' => 'nullable|in:0,1',
            'notifications.notifications_sms' => 'nullable|in:0,1',
            'notifications.notifications_email_enabled' => 'nullable|in:0,1',
            'notifications.notifications_sms_enabled' => 'nullable|in:0,1',
            'notifications.performance_insights_in_app_notification' => 'nullable|in:0,1',
            'notifications.notifications_from_name' => 'nullable|string|max:255',
            'notifications.notifications_from_email' => 'nullable|email|max:255',

            // Chat / Assistant settings
            'chat.assistant_auto_respond' => 'nullable|in:0,1',
            'chat.assistant_auto_respond_admins_when_off' => 'nullable|in:0,1',
            'chat.assistant_chat_stub' => 'nullable|in:0,1',
            'chat.assistant_keyword_intent_routing' => 'nullable|in:0,1',
            'chat.chat_ai_assistance_blocked' => 'nullable|in:0,1',
            'chat.assistant_whatsapp_blacklist_numbers' => 'nullable|string|max:5000',
            'documents.documents_ocr_mode' => 'nullable|string|in:local,ai,hybrid',
            'finance.finance_reporting_currency' => 'nullable|string|size:3',

            // Twilio settings
            'twilio.twilio_sid' => 'nullable|string|max:255',
            'twilio.twilio_token' => 'nullable|string|max:255',
            'twilio.twilio_sms_from' => 'nullable|string|max:255',
            'twilio.twilio_whatsapp_from' => 'nullable|string|max:255',
            // webhook URLs are readonly and not validated

            // WordPress settings
            'wordpress.wordpress_url' => 'nullable|url|max:255',
            'wordpress.wordpress_username' => 'nullable|string|max:255',
            'wordpress.wordpress_application_password' => 'nullable|string|max:255',
            'wordpress.wordpress_cms_sync_enabled' => 'nullable|boolean',
            'wordpress.wordpress_webhook_secret' => 'nullable|string|max:255',

            // WooCommerce settings
            'woocommerce.woocommerce_url' => 'nullable|url|max:255',
            'woocommerce.woocommerce_consumer_key' => 'nullable|string|max:255',
            'woocommerce.woocommerce_consumer_secret' => 'nullable|string|max:255',
            'woocommerce.woocommerce_api_version' => 'nullable|string|in:wc/v1,wc/v2,wc/v3',
            'woocommerce.woocommerce_verify_ssl' => 'nullable|in:0,1',

            // Email settings
            'email.mail_host' => 'nullable|string|max:255',
            'email.mail_port' => 'nullable|integer|between:1,65535',
            'email.mail_username' => 'nullable|string|max:255',
            'email.mail_password' => 'nullable|string|max:255',
            'email.mail_encryption' => 'nullable|string|in:tls,ssl,none',
            'email.mail_from_address' => 'required_with:email|nullable|email|max:255',
            'email.mail_from_name' => 'required_with:email|nullable|string|max:255',
            'email.mailer_from_address' => 'nullable|required_with:email.mailer_from_name|email|max:255',
            'email.mailer_from_name' => 'nullable|required_with:email.mailer_from_address|string|max:255',
            'email.imap_host' => 'nullable|string|max:255',
            'email.imap_port' => 'nullable|integer|between:1,65535',
            'email.imap_username' => 'nullable|string|max:255',
            'email.imap_password' => 'nullable|string|max:255',
            'email.imap_encryption' => 'nullable|string|in:ssl,tls,none',

            // Analytics settings
            'analytics.analytics_property_id' => 'nullable|string|max:255',
            'analytics.analytics_credentials_json' => 'nullable|string',

            // Calendar settings
            'calendar.google_calendar_id' => 'nullable|string|max:255',

            'google.google_contacts_inbound_sync_enabled' => 'nullable|in:0,1',
            'google.google_contacts_outbound_sync_enabled' => 'nullable|in:0,1',
            'google.google_calendar_inbound_sync_enabled' => 'nullable|in:0,1',
            'google.google_calendar_outbound_sync_enabled' => 'nullable|in:0,1',

            'webdav.webdav_contacts_inbound_sync_enabled' => 'nullable|in:0,1',
            'webdav.webdav_contacts_outbound_sync_enabled' => 'nullable|in:0,1',
            'webdav.webdav_calendar_inbound_sync_enabled' => 'nullable|in:0,1',
            'webdav.webdav_calendar_outbound_sync_enabled' => 'nullable|in:0,1',
            'webdav.webdav_tasks_inbound_sync_enabled' => 'nullable|in:0,1',
            'webdav.webdav_tasks_outbound_sync_enabled' => 'nullable|in:0,1',

            // Public assistant shop
            'public_shop.public_catalog_enabled' => 'nullable|in:0,1',

        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return TeamSettingsLabels::validationAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.mail_from_name.required_with' => __('validation.required', ['attribute' => __('app.email_sender_modal_from_name')]),
            'email.mail_from_address.required_with' => __('validation.required', ['attribute' => __('app.email_sender_modal_from_email')]),
            'email.mail_from_address.email' => __('validation.email', ['attribute' => __('app.email_sender_modal_from_email')]),
            'email.mailer_from_name.required_with' => __('validation.required', ['attribute' => __('app.email_sender_modal_from_name')]),
            'email.mailer_from_address.required_with' => __('validation.required', ['attribute' => __('app.email_sender_modal_from_email')]),
            'email.mailer_from_address.email' => __('validation.email', ['attribute' => __('app.email_sender_modal_from_email')]),
            'wordpress.wordpress_url.url' => __('validation.url', ['attribute' => __('team_settings.fields.wordpress_url.label')]),
            'woocommerce.woocommerce_url.url' => __('validation.url', ['attribute' => __('team_settings.fields.woocommerce_url.label')]),
        ];
    }
}
