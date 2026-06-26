<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DomainWithExtension implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(trim((string) $value));

        if ($domain === '')
        {
            return;
        }

        if (
            str_contains($domain, '://')
            || str_contains($domain, '/')
            || str_contains($domain, ' ')
        ) {
            $fail('El dominio no es válido. Debe incluir una extensión (ejemplo: ejemplo.com).');

            return;
        }

        if (! preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,63}$/', $domain))
        {
            $fail('El dominio no es válido. Debe incluir una extensión (ejemplo: ejemplo.com).');
        }
    }
}
