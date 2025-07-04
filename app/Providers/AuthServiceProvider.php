<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Project;
use App\Policies\ClientPolicy;
use App\Policies\ContactPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Enterprise::class => ClientPolicy::class,
        Contact::class => ContactPolicy::class,
        Project::class => ProjectPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
