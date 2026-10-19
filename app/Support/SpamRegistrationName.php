<?php

namespace App\Support;

class SpamRegistrationName
{
    public static function isRandomToken(string $name): bool
    {
        $name = trim($name);

        if ($name === '' || preg_match('/\s/u', $name) === 1)
        {
            return false;
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $name) ?? '';

        if (strlen($letters) < 12)
        {
            return false;
        }

        $transitions = 0;
        $previousUpper = null;
        $length = strlen($letters);

        for ($index = 0; $index < $length; $index++)
        {
            $upper = ctype_upper($letters[$index]);

            if ($previousUpper !== null && $upper !== $previousUpper)
            {
                $transitions++;
            }

            $previousUpper = $upper;
        }

        return $transitions >= 5;
    }
}
