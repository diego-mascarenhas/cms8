<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Clean and format phone numbers according to international standards
     *
     * Examples of expected formats:
     * - 34 722 372 858 → 34722372858
     * - 54 9 11 6728 4492 → 5491167284492
     * - 52 11 7778 8975 → 521177788975
     * - Numbers starting with 15 → Replace with 54911
     * - Avoid double country codes (like 54056...)
     */
    public static function clean($phone, $defaultCountryCode = '54', $debug = false)
    {
        if (empty($phone))
        {
            return null;
        }

        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // If empty after cleaning, return null
        if (empty($cleaned) || trim($cleaned) === '')
        {
            return null;
        }

        // Handle numbers starting with 15 (replace with 54911)
        if (preg_match('/^15(\d{8,})/', $cleaned, $matches))
        {
            $cleaned = '54911'.$matches[1];
            if ($debug)
            {
                \Log::info("Phone transformed from 15xxxx to 54911xxxx: {$phone} → {$cleaned}");
            }

            return $cleaned;
        }

        // Handle numbers with exactly 8 digits (assume Argentine mobile without country/area code)
        if (preg_match('/^\d{8}$/', $cleaned))
        {
            $cleaned = '54911'.$cleaned;
            if ($debug)
            {
                \Log::info("Phone transformed from 8-digit to 54911xxxx: {$phone} → {$cleaned}");
            }

            return $cleaned;
        }

        // Check for double country codes (like 54056... should be 56...)
        // Look for patterns where 54 is followed by another country code
        $doubleCodePatterns = [
            '/^54056(\d+)/' => '56',	 // 54056XXXXX → 56XXXXX (Chile with 0 prefix)
            '/^5456(\d+)/' => '56',	  // 5456XXXXX → 56XXXXX (Chile)
            '/^5434(\d+)/' => '34',	  // 5434XXXXX → 34XXXXX (Spain)
            '/^5452(\d+)/' => '52',	  // 5452XXXXX → 52XXXXX (Mexico)
            '/^5433(\d+)/' => '33',	  // 5433XXXXX → 33XXXXX (France)
            '/^5439(\d+)/' => '39',	  // 5439XXXXX → 39XXXXX (Italy)
            '/^5449(\d+)/' => '49',	  // 5449XXXXX → 49XXXXX (Germany)
            '/^5444(\d+)/' => '44',	  // 5444XXXXX → 44XXXXX (UK)
            '/^541(\d+)/' => '1',		// 541XXXXXX → 1XXXXXX (US/Canada)
        ];

        foreach ($doubleCodePatterns as $pattern => $correctCode)
        {
            if (preg_match($pattern, $cleaned, $matches))
            {
                $corrected = $correctCode.$matches[1];
                if ($debug)
                {
                    \Log::warning("Detected double country code: {$cleaned} → {$corrected}");
                }

                return $corrected;
            }
        }

        // Add default country code if not present and appears to be local number
        if (! self::hasCountryCode($cleaned))
        {
            // If it looks like an Argentine number (starts with 9, 11, or area codes)
            if (preg_match('/^(9|11|221|223|261|351|381|387|388|03\d{2}|0\d{3,4})/', $cleaned))
            {
                $cleaned = $defaultCountryCode.$cleaned;
                if ($debug)
                {
                    \Log::info("Added country code {$defaultCountryCode}: {$phone} → {$cleaned}");
                }
            }
        }

        return $cleaned;
    }

    /**
     * Check if phone number already has a country code
     */
    public static function hasCountryCode($phone)
    {
        // Common country codes
        $countryCodes = [
            '1',   // US/Canada
            '34',  // Spain
            '52',  // Mexico
            '54',  // Argentina
            '56',  // Chile
            '58',  // Venezuela
            '33',  // France
            '39',  // Italy
            '49',  // Germany
            '44',  // UK
        ];

        foreach ($countryCodes as $code)
        {
            if (strpos($phone, $code) === 0)
            {
                // Verify it's a reasonable length for that country
                if ($code === '1' && strlen($phone) >= 11)
                {
                    return true;
                }
                if ($code === '54' && strlen($phone) >= 12)
                {
                    return true;
                }
                if (in_array($code, ['34', '52', '56', '33', '39', '49', '44']) && strlen($phone) >= 10)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Format phone number for WhatsApp (remove + and spaces)
     */
    public static function formatForWhatsApp($phone)
    {
        $cleaned = self::clean($phone);

        return $cleaned ? $cleaned : null;
    }

    /**
     * Format phone number for display (with country code prefix)
     */
    public static function formatForDisplay($phone)
    {
        $cleaned = self::clean($phone);
        if (! $cleaned)
        {
            return null;
        }

        // Add + prefix for international display
        return '+'.$cleaned;
    }

    /**
     * Format phone number for readable display (e.g. +34 722 372 858).
     * Strips WhatsApp JID suffix (e.g. :11) and groups digits with spaces.
     */
    public static function formatForDisplayReadable($phone): ?string
    {
        if (empty($phone))
        {
            return null;
        }
        $raw = preg_replace('/:\d+$/', '', (string) $phone);
        $cleaned = preg_replace('/\D/', '', $raw);
        if ($cleaned === '')
        {
            return null;
        }
        $len = strlen($cleaned);
        if ($len <= 3)
        {
            return '+'.$cleaned;
        }
        // Country code: 1 digit for 1x, else first 2
        $codeLen = (strlen($cleaned) >= 11 && $cleaned[0] === '1') ? 1 : 2;
        $code = substr($cleaned, 0, $codeLen);
        $rest = substr($cleaned, $codeLen);
        $groups = trim(chunk_split($rest, 3, ' '));

        return '+'.$code.' '.$groups;
    }

    /**
     * Check if phone number is valid Argentine mobile
     */
    public static function isArgentineMobile($phone)
    {
        $cleaned = self::clean($phone);
        if (! $cleaned)
        {
            return false;
        }

        // Argentine mobile numbers: 54 9 XX XXXX XXXX
        return preg_match('/^549\d{10}$/', $cleaned) ||
               preg_match('/^54911\d{8}$/', $cleaned); // Buenos Aires mobile
    }

    /**
     * Get country code from phone number
     */
    public static function getCountryCode($phone)
    {
        $cleaned = self::clean($phone);
        if (! $cleaned)
        {
            return null;
        }

        $countryCodes = [
            '1' => 'US/CA',
            '34' => 'ES',
            '52' => 'MX',
            '54' => 'AR',
            '56' => 'CL',
            '58' => 'VE',
            '33' => 'FR',
            '39' => 'IT',
            '49' => 'DE',
            '44' => 'UK',
        ];

        foreach ($countryCodes as $code => $country)
        {
            if (strpos($cleaned, $code) === 0)
            {
                return ['code' => $code, 'country' => $country];
            }
        }

        return null;
    }

    /**
     * Compare two phone strings allowing common WhatsApp variants (exact digits or same trailing digits).
     * Aligns with Chat WhatsApp status when team whatsapp_from and gateway number differ by country code prefix.
     */
    public static function digitsBelongToSameLine(?string $left, ?string $right): bool
    {
        $leftDigits = preg_replace('/[^0-9]/', '', (string) $left);
        $rightDigits = preg_replace('/[^0-9]/', '', (string) $right);
        if ($leftDigits === '' || $rightDigits === '')
        {
            return false;
        }
        if ($leftDigits === $rightDigits)
        {
            return true;
        }

        $minLen = min(strlen($leftDigits), strlen($rightDigits));
        if ($minLen < 8)
        {
            return false;
        }

        $suffixLen = min(10, $minLen);

        return substr($leftDigits, -$suffixLen) === substr($rightDigits, -$suffixLen);
    }
}
