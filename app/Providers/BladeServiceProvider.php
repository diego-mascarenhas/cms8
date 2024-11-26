<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Blade::directive('formatMinutes', function ($expression) {
            return "<?php
                \$hours = floor($expression / 60);
                \$minutes = $expression % 60;
                echo \$hours . 'h ' . \$minutes . 'm';
            ?>";
        });
    }
}