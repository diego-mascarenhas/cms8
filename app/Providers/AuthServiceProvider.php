<?php

namespace App\Providers;

use App\Models\Enterprise;
use App\Policies\ClientPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Enterprise::class => ClientPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
