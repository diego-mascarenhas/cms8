<?php

namespace App\Providers;

use App\View\Components\ClientSelect;
use App\View\Components\CountrySelect;
use App\View\Components\EnterpriseStatusSelect;
use App\View\Components\FareSelect;
use App\View\Components\LanguageSelect;
use App\View\Components\TaskNotifications;
use App\View\Components\TeamUsersSelect;
use App\View\Components\VariantLanguageSelect;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register Blade components
        Blade::component('fare-select', FareSelect::class);
        Blade::component('variant-language-select', VariantLanguageSelect::class);
        Blade::component('client-select', ClientSelect::class);
        Blade::component('country-select', CountrySelect::class);
        Blade::component('enterprise-status-select', EnterpriseStatusSelect::class);
        Blade::component('language-select', LanguageSelect::class);
        Blade::component('team-users-select', TeamUsersSelect::class);
        Blade::component('task-notifications', TaskNotifications::class);
        
        // Register Blade directives
        Blade::directive('formatMinutes', function ($expression) {
            return "<?php
                \$hours = floor($expression / 60);
                \$minutes = $expression % 60;
                echo \$hours . 'h ' . \$minutes . 'm';
            ?>";
        });
    }
}