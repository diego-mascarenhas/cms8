<?php

namespace Idoneo\HumanoCore\Http\Requests;

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

            // Categories settings
            'categories.categories_default_status' => 'nullable|string|in:active,inactive',
            'categories.categories_require_approval' => 'nullable|in:0,1',
            'categories.categories_max_depth' => 'nullable|string|in:1,2,3',
            'categories.categories_allow_multiple_parents' => 'nullable|in:0,1',
            'categories.categories_default_ordering' => 'nullable|string|in:name_asc,name_desc,created_desc,created_asc,custom',

            // Notification settings
            'notifications.notifications_email' => 'nullable|in:0,1',
            'notifications.notifications_sms' => 'nullable|in:0,1',

            // Twilio settings
            'twilio.twilio_sid' => 'nullable|string|max:255',
            'twilio.twilio_token' => 'nullable|string|max:255',
            'twilio.twilio_sms_from' => 'nullable|string|max:255',
            'twilio.twilio_whatsapp_from' => 'nullable|string|max:255',
            // webhook URLs are readonly and not validated

            // Email settings
            'email.mail_host' => 'nullable|string|max:255',
            'email.mail_port' => 'nullable|integer|between:1,65535',
            'email.mail_username' => 'nullable|string|max:255',
            'email.mail_password' => 'nullable|string|max:255',
            'email.mail_encryption' => 'nullable|string|in:tls,ssl,none',
            'email.mail_from_address' => 'nullable|email|max:255',
            'email.mail_from_name' => 'nullable|string|max:255',
            'email.imap_host' => 'nullable|string|max:255',
            'email.imap_port' => 'nullable|integer|between:1,65535',
            'email.imap_username' => 'nullable|string|max:255',
            'email.imap_password' => 'nullable|string|max:255',
            'email.imap_encryption' => 'nullable|string|in:ssl,tls,none',
        ];
    }
}
