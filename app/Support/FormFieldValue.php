<?php

namespace App\Support;

class FormFieldValue
{
    public static function normalize(mixed $value): string
    {
        if (! is_string($value))
        {
            return (string) ($value ?? '');
        }

        $normalized = $value;
        $previous = null;

        while ($previous !== $normalized)
        {
            $previous = $normalized;
            $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $normalized;
    }
}
