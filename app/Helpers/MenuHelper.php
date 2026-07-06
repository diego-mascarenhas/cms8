<?php

namespace App\Helpers;

class MenuHelper
{
    public static function menuLabel(?string $key): string
    {
        if ($key === null || $key === '')
        {
            return '';
        }

        $translated = __($key);

        return is_string($translated) ? $translated : $key;
    }

    public static function getMenuConfig()
    {
        $menuType = env('APP_MENU_TYPE', 'vertical');
        $menuFile = resource_path("menu/{$menuType}Menu.json");

        if (! file_exists($menuFile))
        {
            $menuFile = resource_path('menu/verticalMenu.json');
        }

        return json_decode(file_get_contents($menuFile), true);
    }
}
