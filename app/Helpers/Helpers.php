<?php

namespace App\Helpers;

use Config;

class Helpers
{
    public static function appClasses()
    {
        $data = config('custom.custom');

        // default data array
        $DefaultData = [
            'myLayout' => 'vertical',
            'myTheme' => 'theme-default',
            'myStyle' => 'light',
            'myRTLSupport' => true,
            'myRTLMode' => true,
            'hasCustomizer' => true,
            'showDropdownOnHover' => true,
            'displayCustomizer' => true,
            'contentLayout' => 'compact',
            'headerType' => 'fixed',
            'navbarType' => 'fixed',
            'menuFixed' => true,
            'menuCollapsed' => false,
            'footerFixed' => false,
            'showSearch' => true,
            'showLanguageSelector' => true,
            'showQuickAccess' => true,
            'showNotifications' => true,
            'defaultLanguage' => 'es',
            'customizerControls' => [
                'rtl',
                'style',
                'headerType',
                'contentLayout',
                'layoutCollapsed',
                'showDropdownOnHover',
                'layoutNavbarOptions',
                'themes',
            ],
        ];

        // if any key missing of array from custom.php file it will be merge and set a default value from dataDefault array and store in data variable
        $data = array_merge($DefaultData, $data);

        // All options available in the template
        $allOptions = [
            'myLayout' => ['vertical', 'horizontal', 'blank', 'front'],
            'menuCollapsed' => [true, false],
            'hasCustomizer' => [true, false],
            'showDropdownOnHover' => [true, false],
            'displayCustomizer' => [true, false],
            'contentLayout' => ['compact', 'wide'],
            'headerType' => ['fixed', 'static'],
            'navbarType' => ['fixed', 'static', 'hidden'],
            'myStyle' => ['light', 'dark', 'system'],
            'myTheme' => ['theme-default', 'theme-bordered', 'theme-semi-dark'],
            'myRTLSupport' => [true, false],
            'myRTLMode' => [true, false],
            'menuFixed' => [true, false],
            'footerFixed' => [true, false],
            'showSearch' => [true, false],
            'showLanguageSelector' => [true, false],
            'showQuickAccess' => [true, false],
            'showNotifications' => [true, false],
            'customizerControls' => [],
            'defaultLanguage' => ['en' => 'en', 'es' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'pt'],
        ];

        // if myLayout value empty or not match with default options in custom.php config file then set a default value
        foreach ($allOptions as $key => $value)
        {
            if (array_key_exists($key, $DefaultData))
            {
                if (gettype($DefaultData[$key]) === gettype($data[$key]))
                {
                    // data key should be string
                    if (is_string($data[$key]))
                    {
                        // data key should not be empty
                        if (isset($data[$key]) && $data[$key] !== null)
                        {
                            // data key should not be exist inside allOptions array's sub array
                            if (! array_key_exists($data[$key], $value))
                            {
                                // ensure that passed value should be match with any of allOptions array value
                                $result = array_search($data[$key], $value, 'strict');
                                if (empty($result) && $result !== 0)
                                {
                                    $data[$key] = $DefaultData[$key];
                                }
                            }
                        } else
                        {
                            // if data key not set or
                            $data[$key] = $DefaultData[$key];
                        }
                    }
                } else
                {
                    $data[$key] = $DefaultData[$key];
                }
            }
        }
        $styleVal = $data['myStyle'] == 'dark' ? 'dark' : 'light';
        if (isset($_COOKIE['style']))
        {
            $styleVal = $_COOKIE['style'];
        }
        // layout classes
        $layoutClasses = [
            'layout' => $data['myLayout'],
            'theme' => $data['myTheme'],
            'style' => $styleVal,
            'styleOpt' => $data['myStyle'],
            'rtlSupport' => $data['myRTLSupport'],
            'rtlMode' => $data['myRTLMode'],
            'textDirection' => $data['myRTLMode'],
            'menuCollapsed' => $data['menuCollapsed'],
            'hasCustomizer' => $data['hasCustomizer'],
            'showDropdownOnHover' => $data['showDropdownOnHover'],
            'displayCustomizer' => $data['displayCustomizer'],
            'contentLayout' => $data['contentLayout'],
            'headerType' => $data['headerType'],
            'navbarType' => $data['navbarType'],
            'menuFixed' => $data['menuFixed'],
            'footerFixed' => $data['footerFixed'],
            'showSearch' => $data['showSearch'],
            'showLanguageSelector' => $data['showLanguageSelector'],
            'showQuickAccess' => $data['showQuickAccess'],
            'showNotifications' => $data['showNotifications'],
            'defaultLanguage' => $data['defaultLanguage'],
            'customizerControls' => $data['customizerControls'],
        ];

        // sidebar Collapsed
        if ($layoutClasses['menuCollapsed'] == true)
        {
            $layoutClasses['menuCollapsed'] = 'layout-menu-collapsed';
        }

        // Header Type
        if ($layoutClasses['headerType'] == 'fixed')
        {
            $layoutClasses['headerType'] = 'layout-menu-fixed';
        }
        // Navbar Type
        if ($layoutClasses['navbarType'] == 'fixed')
        {
            $layoutClasses['navbarType'] = 'layout-navbar-fixed';
        } elseif ($layoutClasses['navbarType'] == 'static')
        {
            $layoutClasses['navbarType'] = '';
        } else
        {
            $layoutClasses['navbarType'] = 'layout-navbar-hidden';
        }

        // Menu Fixed
        if ($layoutClasses['menuFixed'] == true)
        {
            $layoutClasses['menuFixed'] = 'layout-menu-fixed';
        }

        // Footer Fixed
        if ($layoutClasses['footerFixed'] == true)
        {
            $layoutClasses['footerFixed'] = 'layout-footer-fixed';
        }

        // RTL Supported template
        if ($layoutClasses['rtlSupport'] == true)
        {
            $layoutClasses['rtlSupport'] = '/rtl';
        }

        // RTL Layout/Mode
        if ($layoutClasses['rtlMode'] == true)
        {
            $layoutClasses['rtlMode'] = 'rtl';
            $layoutClasses['textDirection'] = 'rtl';
        } else
        {
            $layoutClasses['rtlMode'] = 'ltr';
            $layoutClasses['textDirection'] = 'ltr';
        }

        // Show DropdownOnHover for Horizontal Menu
        if ($layoutClasses['showDropdownOnHover'] == true)
        {
            $layoutClasses['showDropdownOnHover'] = true;
        } else
        {
            $layoutClasses['showDropdownOnHover'] = false;
        }

        // To hide/show display customizer UI, not js
        if ($layoutClasses['displayCustomizer'] == true)
        {
            $layoutClasses['displayCustomizer'] = true;
        } else
        {
            $layoutClasses['displayCustomizer'] = false;
        }

        return $layoutClasses;
    }

    public static function updatePageConfig($pageConfigs)
    {
        $demo = 'custom';
        if (isset($pageConfigs))
        {
            if (count($pageConfigs) > 0)
            {
                foreach ($pageConfigs as $config => $val)
                {
                    Config::set('custom.'.$demo.'.'.$config, $val);
                }
            }
        }
    }

    /**
     * Map language codes to country codes for flag display
     * This resolves issues with languages like Japanese (ja) that use different country codes (jp) for flags
     *
     * @param  string  $languageCode  ISO language code
     * @return string Country code to use for flag display
     */
    public static function getLanguageFlag($languageCode)
    {
        $languageToCountryMap = [
            'ja' => 'jp', // Japanese -> Japan
            'ko' => 'kr', // Korean -> South Korea
            'zh' => 'cn', // Chinese -> China
            'en' => 'gb', // English -> Great Britain (default)
            'ar' => 'sa', // Arabic -> Saudi Arabia
            // Add more mappings as needed
        ];

        return $languageToCountryMap[$languageCode] ?? $languageCode;
    }

    /**
     * Format money amount with currency
     *
     * @param  float  $amount  Amount to format
     * @param  string  $currency  Currency code (USD, ARS, EUR, etc.)
     * @param  int  $decimals  Number of decimal places
     * @return string Formatted money string
     */
    public static function formatMoney($amount, $currency = 'USD', $decimals = 2)
    {
        $symbols = [
            'USD' => '$',
            'ARS' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'BRL' => 'R$',
            'MXN' => '$',
        ];

        $symbol = $symbols[$currency] ?? $currency.' ';
        $formattedAmount = number_format($amount, $decimals, '.', ',');

        return $symbol.$formattedAmount;
    }

    /**
     * Format money amount with currency using Laravel Cashier Money class
     *
     * @param  int  $amount  Amount in cents
     * @param  string  $currency  Currency code
     * @return string Formatted money string
     */
    public static function formatMoneyFromCents($amount, $currency = 'USD')
    {
        return \Laravel\Cashier\Cashier::formatAmount($amount, $currency);
    }

    /**
     * Format large numbers in a compact way (1.2K, 1.5M, etc.)
     */
    public static function formatCompactNumber($number)
    {
        if ($number < 1000)
        {
            return number_format($number);
        }

        if ($number < 1000000)
        {
            return round($number / 1000, 1).'K';
        }

        if ($number < 1000000000)
        {
            return round($number / 1000000, 1).'M';
        }

        return round($number / 1000000000, 1).'B';
    }

    /**
     * Return the URL for a logo variant, checking file existence and falling back when missing.
     * Variants: dark, light (full logo); iso, iso_dark, iso_light (iso/small logo).
     */
    public static function logoAsset(string $variant): string
    {
        $logo = config('variables.logo');
        $variantToPath = [
            'dark' => $logo['path_dark'] ?? 'assets/logo-dark.svg',
            'light' => $logo['path_light'] ?? 'assets/logo-light.svg',
            'iso' => $logo['path_iso'] ?? 'assets/logo-iso.svg',
            'iso_dark' => $logo['path_iso_dark'] ?? 'assets/logo-iso-dark.svg',
            'iso_light' => $logo['path_iso_light'] ?? 'assets/logo-iso-light.svg',
        ];
        $fallbackForVariant = [
            'dark' => $logo['fallback'] ?? 'assets/logo.svg',
            'light' => $logo['fallback'] ?? 'assets/logo.svg',
            'iso' => $logo['iso_fallback'] ?? 'assets/logo-iso.svg',
            'iso_dark' => $logo['iso_fallback'] ?? 'assets/logo-iso.svg',
            'iso_light' => $logo['iso_fallback'] ?? 'assets/logo-iso.svg',
        ];
        if (! array_key_exists($variant, $variantToPath))
        {
            return asset($logo['fallback'] ?? 'assets/logo.svg');
        }
        $path = $variantToPath[$variant];
        $fallback = $fallbackForVariant[$variant];
        $fullPath = public_path($path);

        return file_exists($fullPath) ? asset($path) : asset($fallback);
    }
}
