<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\LanguageVariant;
use App\Models\Project;
use App\Models\Service;
use App\Models\Software;
use App\Policies\ClientPolicy;
use App\Policies\ContactPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LanguageVariantPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SoftwarePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Enterprise::class => ClientPolicy::class,
        Contact::class => ContactPolicy::class,
        Project::class => ProjectPolicy::class,
        Service::class => ServicePolicy::class,
        Invoice::class => InvoicePolicy::class,
        LanguageVariant::class => LanguageVariantPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
