<?php

namespace App\Support;

class TeamSettingsLabels
{
    public static function groupTitle(string $group): string
    {
        $key = "team_settings.groups.{$group}.title";

        if ($group === 'email')
        {
            return __('app.team_setting_mailer_email_title');
        }

        $title = __($key);

        return $title === $key ? ucfirst(str_replace('-', ' ', $group)) : $title;
    }

    public static function groupSubtitle(string $group): string
    {
        if ($group === 'email')
        {
            return __('app.team_setting_mailer_email_subtitle');
        }

        $key = "team_settings.groups.{$group}.subtitle";
        $subtitle = __($key);

        return $subtitle === $key ? '' : $subtitle;
    }

    public static function groupSavedMessage(string $group): string
    {
        if ($group === 'email')
        {
            return __('app.team_setting_mailer_saved');
        }

        return __('team_settings.group_saved', ['group' => self::groupTitle($group)]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $config
     * @return array<string, array<string, mixed>>
     */
    public static function localizeConfig(array $config): array
    {
        foreach ($config as $groupKey => &$group)
        {
            $group['title'] = self::groupTitle($groupKey);

            foreach ($group['settings'] as $fieldKey => &$setting)
            {
                self::localizeField($fieldKey, $setting);

                if (isset($setting['options']) && is_array($setting['options']))
                {
                    $setting['options'] = self::localizeOptions($fieldKey, $setting['options']);
                }
            }
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $setting
     */
    private static function localizeField(string $fieldKey, array &$setting): void
    {
        foreach (['label', 'help', 'placeholder'] as $attribute)
        {
            $translationKey = "team_settings.fields.{$fieldKey}.{$attribute}";
            $translated = __($translationKey);

            if ($translated !== $translationKey)
            {
                $setting[$attribute] = $translated;
            }
        }
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function localizeOptions(string $fieldKey, array $options): array
    {
        $localized = [];

        foreach ($options as $value => $label)
        {
            $optionKey = self::optionTranslationKey($fieldKey, (string) $value);
            $translated = __("team_settings.options.{$optionKey}");
            $localized[$value] = $translated !== "team_settings.options.{$optionKey}" ? $translated : $label;
        }

        return $localized;
    }

    private static function optionTranslationKey(string $fieldKey, string $value): string
    {
        if ($fieldKey === 'categories_default_status')
        {
            return $value === 'active' ? 'active' : 'inactive';
        }

        if ($fieldKey === 'categories_max_depth')
        {
            return match ($value)
            {
                '1' => 'level_1',
                '2' => 'level_2',
                '3' => 'level_3',
                default => $value,
            };
        }

        if ($fieldKey === 'categories_default_ordering')
        {
            return match ($value)
            {
                'name_asc' => 'name_asc',
                'name_desc' => 'name_desc',
                'created_desc' => 'created_desc',
                'created_asc' => 'created_asc',
                'custom' => 'custom',
                default => $value,
            };
        }

        if ($fieldKey === 'api_token_abilities')
        {
            return match ($value)
            {
                '*' => 'abilities_all',
                'read' => 'abilities_read',
                'write' => 'abilities_write',
                'read,write' => 'abilities_read_write',
                default => $value,
            };
        }

        if (str_contains($fieldKey, 'encryption') || $fieldKey === 'mail_encryption' || $fieldKey === 'imap_encryption')
        {
            return match ($value)
            {
                'tls' => 'tls',
                'ssl' => 'ssl',
                'none' => 'none',
                default => $value,
            };
        }

        if ($fieldKey === 'woocommerce_api_version')
        {
            return match ($value)
            {
                'wc/v3' => 'wc_v3',
                'wc/v2' => 'wc_v2',
                'wc/v1' => 'wc_v1',
                default => $value,
            };
        }

        if ($fieldKey === 'fiscal_platform')
        {
            return match ($value)
            {
                '' => 'fiscal_auto',
                'cuentica' => 'fiscal_cuentica',
                'arca' => 'fiscal_arca',
                'none' => 'fiscal_none',
                default => $value,
            };
        }

        if ($fieldKey === 'fiscal_country')
        {
            return match ($value)
            {
                '' => 'not_set',
                'ES' => 'country_es',
                'AR' => 'country_ar',
                default => $value,
            };
        }

        return $value;
    }

    public static function sectionTitle(string $section, string $groupKey = ''): ?string
    {
        $map = [
            'general' => 'team_settings.sections.notifications_general',
            'sender' => 'team_settings.sections.notifications_sender',
            'performance_insights' => 'team_settings.sections.notifications_performance_insights',
            'connection' => $groupKey === 'wordpress'
                ? 'team_settings.sections.wordpress_connection'
                : 'team_settings.sections.woocommerce_connection',
            'credentials' => 'team_settings.sections.woocommerce_credentials',
            'security' => 'team_settings.sections.woocommerce_security',
            'outgoing' => 'team_settings.sections.email_outgoing',
            'incoming' => 'team_settings.sections.email_incoming',
            'inbound' => $groupKey === 'google' || $groupKey === 'webdav'
                ? ($groupKey === 'google' ? 'team_settings.sections.google_inbound' : 'team_settings.sections.webdav_inbound')
                : null,
            'outbound' => $groupKey === 'google' || $groupKey === 'webdav'
                ? ($groupKey === 'google' ? 'team_settings.sections.google_outbound' : 'team_settings.sections.webdav_outbound')
                : null,
            'plan' => 'team_settings.sections.email_plans_plan',
            'limits' => 'team_settings.sections.email_plans_limits',
            'contacts' => 'team_settings.sections.email_plans_contacts',
            'reset' => 'team_settings.sections.email_plans_reset',
        ];

        $key = $map[$section] ?? null;

        if ($key === null)
        {
            return null;
        }

        $title = __($key);

        return $title === $key ? null : $title;
    }

    /**
     * @return array<string, string>
     */
    public static function validationAttributes(): array
    {
        $attributes = [];

        $groups = [
            'stripe' => ['stripe_public', 'stripe_secret', 'stripe_webhook'],
            'fiscal' => ['fiscal_platform', 'fiscal_country'],
            'affiliates' => ['affiliate_commission_percent'],
            'cuentica' => ['cuentica_api_token', 'cuentica_invoice_serie', 'cuentica_inbound_sync_enabled'],
            'categories' => ['categories_default_status', 'categories_require_approval', 'categories_max_depth', 'categories_allow_multiple_parents', 'categories_default_ordering'],
            'notifications' => ['notifications_email_enabled', 'notifications_sms_enabled', 'performance_insights_in_app_notification', 'notifications_from_name', 'notifications_from_email'],
            'chat' => ['assistant_auto_respond', 'assistant_auto_respond_admins_when_off', 'assistant_chat_stub', 'assistant_keyword_intent_routing', 'chat_ai_assistance_blocked', 'assistant_whatsapp_blacklist_numbers'],
            'documents' => ['documents_ocr_mode'],
            'finance' => ['finance_reporting_currency'],
            'twilio' => ['twilio_sid', 'twilio_token', 'twilio_sms_from', 'twilio_whatsapp_from'],
            'wordpress' => ['wordpress_url', 'wordpress_username', 'wordpress_application_password'],
            'woocommerce' => ['woocommerce_url', 'woocommerce_consumer_key', 'woocommerce_consumer_secret', 'woocommerce_api_version', 'woocommerce_verify_ssl'],
            'email' => ['mail_from_name', 'mail_from_address', 'mailer_from_name', 'mailer_from_address', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption'],
            'analytics' => ['analytics_property_id', 'analytics_credentials_json'],
            'calendar' => ['google_calendar_id'],
            'google' => ['google_contacts_inbound_sync_enabled', 'google_contacts_outbound_sync_enabled', 'google_calendar_inbound_sync_enabled', 'google_calendar_outbound_sync_enabled'],
            'webdav' => ['webdav_contacts_inbound_sync_enabled', 'webdav_contacts_outbound_sync_enabled', 'webdav_calendar_inbound_sync_enabled', 'webdav_calendar_outbound_sync_enabled', 'webdav_tasks_inbound_sync_enabled', 'webdav_tasks_outbound_sync_enabled'],
            'public_shop' => ['public_catalog_enabled'],
        ];

        foreach ($groups as $group => $fields)
        {
            foreach ($fields as $field)
            {
                $labelKey = "team_settings.fields.{$field}.label";
                $label = __($labelKey);

                if ($label === $labelKey)
                {
                    $appKey = "app.team_setting_{$field}";
                    $appLabel = __($appKey);
                    $label = $appLabel !== $appKey ? $appLabel : ucfirst(str_replace('_', ' ', $field));
                }

                $attributes["{$group}.{$field}"] = $label;
            }
        }

        $attributes['email.mail_from_name'] = __('app.email_sender_modal_from_name');
        $attributes['email.mail_from_address'] = __('app.email_sender_modal_from_email');
        $attributes['email.mailer_from_name'] = __('app.email_sender_modal_from_name');
        $attributes['email.mailer_from_address'] = __('app.email_sender_modal_from_email');

        return $attributes;
    }
}
