<?php

namespace App\Providers;

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Certification;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseDepartment;
use App\Models\Fare;
use App\Models\Invoice;
use App\Models\LanguageVariant;
use App\Models\Mailbox;
use App\Models\Multimedia;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Service;
use App\Models\Software;
use App\Models\Stylebook;
use App\Models\TeamFile;
use App\Models\TeamPassword;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Policies\CalendarEventPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CertificationPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ContactPolicy;
use App\Policies\EnterpriseDepartmentPolicy;
use App\Policies\FarePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LanguageVariantPolicy;
use App\Policies\MailboxPolicy;
use App\Policies\MultimediaPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\PaymentAccountPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PostPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\PromptPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SoftwarePolicy;
use App\Policies\StyleBookPolicy;
use App\Policies\TeamFilePolicy;
use App\Policies\TeamPasswordPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserDailyPerformanceInsightPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CalendarEvent::class => CalendarEventPolicy::class,
        Category::class => CategoryPolicy::class,
        Certification::class => CertificationPolicy::class,
        EnterpriseDepartment::class => EnterpriseDepartmentPolicy::class,
        Enterprise::class => ClientPolicy::class,
        Contact::class => ContactPolicy::class,
        Fare::class => FarePolicy::class,
        Product::class => ProductPolicy::class,
        Project::class => ProjectPolicy::class,
        Service::class => ServicePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        PaymentAccount::class => PaymentAccountPolicy::class,
        LanguageVariant::class => LanguageVariantPolicy::class,
        Prompt::class => PromptPolicy::class,
        Multimedia::class => MultimediaPolicy::class,
        Opportunity::class => OpportunityPolicy::class,
        Software::class => SoftwarePolicy::class,
        Stylebook::class => StyleBookPolicy::class,
        Post::class => PostPolicy::class,
        Mailbox::class => MailboxPolicy::class,
        Ticket::class => TicketPolicy::class,
        TeamFile::class => TeamFilePolicy::class,
        TeamPassword::class => TeamPasswordPolicy::class,
        UserDailyPerformanceInsight::class => UserDailyPerformanceInsightPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        Gate::define('view-language-variants', function ($user)
        {
            return $user->hasRole('admin');
        });

        Gate::define('view-performance-insights', function ($user)
        {
            return $user->can('viewAny', UserDailyPerformanceInsight::class);
        });

        Gate::define('access-billing-modules', function (User $user): bool
        {
            return $user->canAccessBilling();
        });
    }
}
