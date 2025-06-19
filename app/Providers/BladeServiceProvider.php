<?php

namespace App\Providers;

use App\View\Components\FareSelect;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register Blade components
        Blade::component('fare-select', FareSelect::class);
        
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