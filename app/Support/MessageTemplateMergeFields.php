<?php

namespace App\Support;

class MessageTemplateMergeFields
{
    /**
     * @return list<array{token: string, label: string}>
     */
    public static function forUi(): array
    {
        return [
            ['token' => '{{name}}', 'label' => __('app.message_merge_field_name')],
            ['token' => '{{surname}}', 'label' => __('app.message_merge_field_surname')],
            ['token' => '{{full_name}}', 'label' => __('app.message_merge_field_full_name')],
            ['token' => '{{email}}', 'label' => __('app.message_merge_field_email')],
            ['token' => '{{phone}}', 'label' => __('app.message_merge_field_phone')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valuesForContact(object $contact): array
    {
        $name = (string) ($contact->name ?? '');
        $surname = (string) ($contact->surname ?? '');
        $fullName = trim($name.' '.$surname);
        $email = (string) ($contact->email ?? '');
        $phone = (string) ($contact->phone ?? '');

        return [
            '{{name}}' => $name,
            '{{surname}}' => $surname,
            '{{full_name}}' => $fullName,
            '{{contact_name}}' => $fullName,
            '{{email}}' => $email,
            '{{phone}}' => $phone,
        ];
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
        return self::valuesForContact((object) [
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+34600000000',
        ]);
    }
}
