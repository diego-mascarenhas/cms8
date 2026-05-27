<?php

namespace App\Support;

class MessageTemplateMergeFields
{
    /**
     * @return list<array{key: string, label_key: string, tokens: array<string, string>, aliases?: list<string>}>
     */
    private static function fields(): array
    {
        return [
            [
                'key' => 'name',
                'label_key' => 'message_merge_field_name',
                'tokens' => [
                    'en' => '{{name}}',
                    'es' => '{{nombre}}',
                ],
            ],
            [
                'key' => 'surname',
                'label_key' => 'message_merge_field_surname',
                'tokens' => [
                    'en' => '{{surname}}',
                    'es' => '{{apellido}}',
                ],
            ],
            [
                'key' => 'full_name',
                'label_key' => 'message_merge_field_full_name',
                'tokens' => [
                    'en' => '{{full_name}}',
                    'es' => '{{nombre_completo}}',
                ],
                'aliases' => ['{{contact_name}}', '{{nombre_contacto}}'],
            ],
            [
                'key' => 'email',
                'label_key' => 'message_merge_field_email',
                'tokens' => [
                    'en' => '{{email}}',
                    'es' => '{{email}}',
                ],
            ],
            [
                'key' => 'phone',
                'label_key' => 'message_merge_field_phone',
                'tokens' => [
                    'en' => '{{phone}}',
                    'es' => '{{telefono}}',
                ],
            ],
        ];
    }

    private static function uiLocaleKey(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return str_starts_with($locale, 'es') ? 'es' : 'en';
    }

    /**
     * @return list<array{token: string, label: string}>
     */
    public static function forUi(?string $locale = null): array
    {
        $localeKey = self::uiLocaleKey($locale);

        return array_map(static function (array $field) use ($localeKey): array
        {
            return [
                'token' => $field['tokens'][$localeKey],
                'label' => __('app.'.$field['label_key']),
            ];
        }, self::fields());
    }

    /**
     * @return array<string, string>
     */
    private static function contactValues(object $contact): array
    {
        $name = (string) ($contact->name ?? '');
        $surname = (string) ($contact->surname ?? '');
        $fullName = trim($name.' '.$surname);

        return [
            'name' => $name,
            'surname' => $surname,
            'full_name' => $fullName,
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valuesForContact(object $contact): array
    {
        $values = self::contactValues($contact);
        $map = [];

        foreach (self::fields() as $field)
        {
            $value = $values[$field['key']];

            foreach ($field['tokens'] as $token)
            {
                $map[$token] = $value;
            }

            foreach ($field['aliases'] ?? [] as $token)
            {
                $map[$token] = $value;
            }
        }

        return $map;
    }

    public static function replace(string $content, object $contact): string
    {
        foreach (self::valuesForContact($contact) as $token => $value)
        {
            $content = str_replace($token, $value, $content);
        }

        return $content;
    }

    /**
     * @return array<string, string>
     */
    public static function sampleValues(): array
    {
        return self::valuesForContact(self::sampleContact());
    }

    public static function sampleContact(): object
    {
        return (object) [
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+34600000000',
        ];
    }
}
