<?php

namespace App\Helpers;

class MenuHelper
{
    public static function getMenuConfig()
    {
        $menuType = env('APP_MENU_TYPE', 'vertical');
        $menuFile = resource_path("menu/{$menuType}Menu.json");

        if (!file_exists($menuFile)) {
            $menuFile = resource_path("menu/verticalMenu.json");
        }

        return json_decode(file_get_contents($menuFile), true);
    }
} 