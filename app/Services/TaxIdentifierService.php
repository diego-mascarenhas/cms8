<?php

namespace App\Services;

/**
 * Normalizes and validates tax identifiers for countries we support, and maps
 * them to the Stripe Customer Tax ID "type" values.
 */
class TaxIdentifierService
{
    private const SPAIN_DNI_NIE_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKEO';

    /**
     * Uppercase and strip non-alphanumeric characters.
     */
    public function normalize(string $value): string
    {
        $clean = preg_replace('/[^0-9A-Za-z]/', '', $value);

        return strtoupper($clean ?? '');
    }

    public function isValidForCountry(string $country, string $normalizedValue): bool
    {
        return match (strtoupper($country))
        {
            'AR' => $this->isValidArgentineCuit($normalizedValue),
            'ES' => $this->isValidSpain($normalizedValue),
            'MX' => $this->isValidMexicanRfc($normalizedValue),
            'CL' => $this->isValidChileanRut($normalizedValue),
            'CO' => $this->isValidColombianNit($normalizedValue),
            'PE' => $this->isValidPeruvianRuc($normalizedValue),
            'UY' => $this->isValidUruguayanRut($normalizedValue),
            default => strlen($normalizedValue) >= 5,
        };
    }

    /**
     * @return null When no Stripe tax id type should be set for this country
     */
    public function resolveStripeTaxIdType(string $country, string $normalizedValue): ?string
    {
        if ($normalizedValue === '')
        {
            return null;
        }

        $country = strtoupper($country);

        return match ($country)
        {
            'AR' => 'ar_cuit',
            'ES' => $this->isSpainEuVatNumber($normalizedValue) ? 'eu_vat' : 'es_cif',
            'MX' => 'mx_rfc',
            'CL' => 'cl_tin',
            'CO' => 'co_nit',
            'PE' => 'pe_ruc',
            'UY' => 'uy_ruc',
            'US' => 'us_ein',
            default => null,
        };
    }

    private function isSpainEuVatNumber(string $normalized): bool
    {
        if (! preg_match('/^ES[0-9A-Z]{9}$/', $normalized))
        {
            return false;
        }

        $body = substr($normalized, 2);

        return $this->isValidSpainDomesticNifCifNie($body);
    }

    private function isValidSpain(string $normalized): bool
    {
        if (preg_match('/^ES([0-9A-Z]{9})$/', $normalized, $m))
        {
            return $this->isValidSpainDomesticNifCifNie($m[1]);
        }

        return $this->isValidSpainDomesticNifCifNie($normalized);
    }

    private function isValidSpainDomesticNifCifNie(string $n): bool
    {
        if (preg_match('/^\d{8}[A-Z]$/', $n))
        {
            return $this->spainDniCheckLetter($n);
        }
        if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $n))
        {
            return $this->spainNieCheckLetter($n);
        }
        if (preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[\dA-J]$/', $n))
        {
            return $this->spainCifCheckControl($n);
        }

        return false;
    }

    private function spainDniCheckLetter(string $dni): bool
    {
        if (! preg_match('/^(\d{8})([A-Z])$/', $dni, $m))
        {
            return false;
        }
        $idx = (int) $m[1] % 23;
        $expected = self::SPAIN_DNI_NIE_LETTERS[$idx] ?? null;

        return $expected !== null && $m[2] === $expected;
    }

    private function spainNieCheckLetter(string $nie): bool
    {
        if (! preg_match('/^([XYZ])(\d{7})([A-Z])$/', $nie, $m))
        {
            return false;
        }
        $x = str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], $m[1]);
        $num = (int) ($x.$m[2]);
        $expected = self::SPAIN_DNI_NIE_LETTERS[$num % 23] ?? null;

        return $expected !== null && $m[3] === $expected;
    }

    /**
     * CIF (legal entity) check character — AEAT parity algorithm.
     */
    private function spainCifCheckControl(string $cif): bool
    {
        $cif = strtoupper($cif);
        if (! preg_match('/^([ABCDEFGHJNPQRSUVW])\d{7}([\dA-J])$/', $cif))
        {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 7; $i++)
        {
            $d = (int) $cif[$i + 1];
            if ($i % 2 === 0)
            {
                $d *= 2;
            }
            $sum += (int) floor($d / 10) + ($d % 10);
        }
        $control = 10 - ($sum % 10);
        if ($control === 10)
        {
            $control = 0;
        }
        $last = $cif[8];
        $map = 'JABCDEFGHI';
        $type = $cif[0];
        if (in_array($type, ['A', 'B', 'E', 'H'], true))
        {
            return isset($map[$control]) && $last === $map[$control];
        }

        return (string) $control === $last;
    }

    /**
     * CUIT (CUIT) — 11 digits with AFIP check digit. Accepts 11 digit string only.
     */
    public function isValidArgentineCuit(string $cuit): bool
    {
        if (strlen($cuit) !== 11 || ! ctype_digit($cuit))
        {
            return false;
        }

        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $acum = 0;
        for ($i = 0; $i < 10; $i++)
        {
            $acum += (int) $cuit[$i] * $mult[$i];
        }
        $verif = 11 - ($acum % 11);
        if ($verif === 11)
        {
            $verif = 0;
        } elseif ($verif === 10)
        {
            $verif = 9;
        }

        return (int) $cuit[10] === $verif;
    }

    private function isValidMexicanRfc(string $rfc): bool
    {
        return strlen($rfc) >= 12 && strlen($rfc) <= 13 && preg_match('/^[A-Z0-9]+$/', $rfc) === 1;
    }

    private function isValidChileanRut(string $rut): bool
    {
        return strlen($rut) >= 8 && strlen($rut) <= 10 && preg_match('/^[0-9]{7,9}[0-9K]$/i', $rut) === 1;
    }

    private function isValidColombianNit(string $nit): bool
    {
        return strlen($nit) >= 9 && strlen($nit) <= 10 && ctype_digit($nit);
    }

    private function isValidPeruvianRuc(string $ruc): bool
    {
        return strlen($ruc) === 11 && ctype_digit($ruc);
    }

    private function isValidUruguayanRut(string $rut): bool
    {
        return strlen($rut) === 12 && ctype_digit($rut);
    }
}
