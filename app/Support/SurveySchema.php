<?php

namespace App\Support;

class SurveySchema
{
    /**
     * Document shape used by the survey module. A survey is not tied to an action:
     * title plus a list of fields, each with a stable key and radio options.
     *
     * @param  list<array{label: string, value: string}>  $options
     * @return array<string, mixed>
     */
    public static function field(
        string $key,
        string $label,
        array $options,
        bool $required = false,
        bool $anonymous = false,
        ?string $help = null,
        bool $forCertificate = false,
    ): array {
        return [
            'key' => $key,
            'type' => 'radio',
            'label' => $label,
            'help' => $help,
            'layout' => 'vertical',
            'required' => $required,
            'anonymous' => $anonymous,
            'for_certificate' => $forCertificate,
            'options' => array_values($options),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{title: string, fields: list<array<string, mixed>>}
     */
    public static function document(string $title, array $fields): array
    {
        return [
            'title' => $title,
            'fields' => array_values($fields),
        ];
    }

    /**
     * Build the JSON document from one cms7 event and its questions and options.
     *
     * cms7 stores the respondent when anonima is 0, so the JSON flag is the inverse.
     *
     * @param  list<array<string, mixed>|object>  $questions
     * @param  array<int, list<array<string, mixed>|object>>  $optionsByQuestion
     * @return array{title: string, fields: list<array<string, mixed>>}
     */
    public static function fromLegacyEvent(string $title, array $questions, array $optionsByQuestion): array
    {
        $fields = [];

        foreach ($questions as $question)
        {
            $question = (array) $question;
            $questionId = (int) ($question['id'] ?? 0);

            if ($questionId === 0 || (int) ($question['estado'] ?? 1) <= 0)
            {
                continue;
            }

            $options = [];

            foreach ($optionsByQuestion[$questionId] ?? [] as $option)
            {
                $option = (array) $option;

                if ((int) ($option['estado'] ?? 1) <= 0)
                {
                    continue;
                }

                $label = trim((string) ($option['titulo'] ?? ''));

                if ($label === '')
                {
                    continue;
                }

                $options[] = [
                    'label' => $label,
                    'value' => (string) $option['id'],
                ];
            }

            $fields[] = self::field(
                'q'.$questionId,
                trim((string) ($question['titulo'] ?? '')),
                $options,
                (int) ($question['obligatoria'] ?? 0) === 1,
                (int) ($question['anonima'] ?? 0) === 1,
                self::blankToNull($question['subtitulo'] ?? null),
                (int) ($question['para_certificar'] ?? 0) === 1,
            );
        }

        return self::document($title, $fields);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{title: string, fields: list<array<string, mixed>>}
     */
    public static function fromForm(string $title, array $fields): array
    {
        $built = [];
        $usedKeys = [];

        foreach (array_values($fields) as $index => $field)
        {
            if (! is_array($field))
            {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $options = self::optionsFromField($field);

            if ($label === '' || $options === [])
            {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));

            if ($key === '')
            {
                $key = 'q'.($index + 1);
            }

            $base = $key;
            $suffix = 2;

            while (isset($usedKeys[$key]))
            {
                $key = $base.'_'.$suffix;
                $suffix++;
            }

            $usedKeys[$key] = true;

            $built[] = self::field(
                $key,
                $label,
                $options,
                filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                filter_var($field['anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
                self::blankToNull($field['help'] ?? null),
                filter_var($field['for_certificate'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        }

        return self::document($title, $built);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array{label: string, value: string}>
     */
    private static function optionsFromField(array $field): array
    {
        $options = [];

        foreach (array_values($field['options'] ?? []) as $index => $option)
        {
            if (! is_array($option))
            {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));

            if ($label === '')
            {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));

            if ($value === '')
            {
                $value = self::nextOptionValue($options, $index + 1);
            }

            $options[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) ($field['options_text'] ?? '')) ?: [];

        foreach ($lines as $line)
        {
            $label = trim($line);

            if ($label === '')
            {
                continue;
            }

            $options[] = [
                'label' => $label,
                'value' => self::nextOptionValue($options, count($options) + 1),
            ];
        }

        return $options;
    }

    /**
     * @param  list<array{label: string, value: string}>  $options
     */
    private static function nextOptionValue(array $options, int $preferred): string
    {
        $used = array_column($options, 'value');
        $value = (string) $preferred;

        while (in_array($value, $used, true))
        {
            $preferred++;
            $value = (string) $preferred;
        }

        return $value;
    }

    private static function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
