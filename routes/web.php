<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApolloController;
use App\Http\Controllers\apps\Calendar;
use App\Http\Controllers\apps\InvoiceList;
use App\Http\Controllers\AssistantActivityController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
// use App\Http\Controllers\AcademyController; // Now using humano-academy package
use App\Http\Controllers\CampaignsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactInteractionController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ContentFieldConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailPlanController;
use App\Http\Controllers\EmailPlansManagementController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EnterpriseDepartmentController;
use App\Http\Controllers\EnterpriseOrganizationController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FareController;
use App\Http\Controllers\FinancialDashboardController;
use App\Http\Controllers\GoogleIntegrationController;
use App\Http\Controllers\GooglePlacesController;
use App\Http\Controllers\GoogleSyncedPreviewController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HostingController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LegalDocumentsController;
use App\Http\Controllers\List60Controller;
use App\Http\Controllers\MailController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessageTrackingController;
use App\Http\Controllers\MultimediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTrackingController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OvhApiController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\pages\AccountSettingsAccount;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentSubscriptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\ProspectSearchController;
use App\Http\Controllers\PublicShopController;
use App\Http\Controllers\RegistrationBillingController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SLAController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StylebookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamFileController;
use App\Http\Controllers\TeamInvitationConfirmController;
use App\Http\Controllers\TeamMailboxController;
use App\Http\Controllers\TeamPasswordController;
use App\Http\Controllers\TeamSettingController;
use App\Http\Controllers\TeamSocialConnectionController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TimeController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFareController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// auth
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function ()
{
    Route::get('/dashboard', function ()
    {
        return view('dashboard');
    })->name('dashboard');
});

// locale
Route::get('lang/{locale}', [LanguageController::class, 'swap']);

// Public API routes (must be before auth group)
Route::get('/project/fare-units', [ProjectController::class, 'getFareUnits'])
    ->name('project.get-fare-units');
Route::get('/team-file/share/{hash}', [TeamFileController::class, 'shared'])->name('team-file.shared');
Route::get('/password/share/{token}', [TeamPasswordController::class, 'showPasswordShare'])->name('passwords.share.consume');
Route::post('/password/share/{token}', [TeamPasswordController::class, 'revealPasswordShare'])->name('passwords.share.reveal');

Route::middleware('throttle:120,1')->get('/shop/{slug}', [PublicShopController::class, 'show'])
    ->name('public-shop.show');

// main
Route::get('/', [HomeController::class, 'index']);
Route::get('/home', [PageController::class, 'home'])->name('home');

Route::get('/landing', fn () => view('landing-widget'))->name('landing');

Route::get('/pricing', [\App\Http\Controllers\front_pages\Pricing::class, 'index'])->name('pricing');
Route::get('/front-pages/pricing', [\App\Http\Controllers\front_pages\Pricing::class, 'index'])->name('front-pages.pricing');
Route::get('/pricing/checkout/complete', \App\Http\Controllers\PaymentLinkCheckoutCompleteController::class)
    ->middleware('throttle:payment-link-checkout')
    ->name('pricing.checkout.complete');

Route::get('/wapify', function ()
{
    \Illuminate\Support\Facades\App::setLocale('es_AR');

    return view('homes.wapify');
})->name('wapify');

Route::get('/wapify/ayuda', [ManualController::class, 'ayuda'])->name('wapify.ayuda');
Route::redirect('/ayuda', '/wapify/ayuda', 301)->name('ayuda');

Route::get('/landing/gracias', fn () => view('landing-gracias'))->name('landing.gracias');
Route::get('/launch/{token?}', fn (?string $token = null) => view('landing-business-creation', ['token' => $token]))
    ->name('landing.business-creation');

Route::get('/assistant/activity', [AssistantActivityController::class, 'index'])->name('assistant.activity')->middleware('auth');
Route::get('/assistant/activity/data', [AssistantActivityController::class, 'data'])->name('assistant.activity.data')->middleware('auth');
Route::get('/assistant/documents', [AssistantActivityController::class, 'documents'])->name('assistant.documents')->middleware('auth');
Route::get('/assistant/documents/data', [AssistantActivityController::class, 'documentsData'])->name('assistant.documents.data')->middleware('auth');
Route::get('/assistant/documents/{documentIngestion}', [AssistantActivityController::class, 'documentShow'])->name('assistant.documents.show')->middleware('auth');
Route::post('/assistant/documents/{documentIngestion}/reprocess', [AssistantActivityController::class, 'documentReprocess'])->name('assistant.documents.reprocess')->middleware('auth');
Route::post('/assistant/documents/{documentIngestion}/mark-ingested', [AssistantActivityController::class, 'documentMarkIngested'])->name('assistant.documents.mark-ingested')->middleware('auth');
Route::get('/assistant/{key?}', fn (?string $key = null) => view('assistant-demo', ['promptKey' => $key]))->name('assistant');
Route::redirect('/try-assistant', '/assistant')->name('assistant-demo');

Route::redirect('/propspect/search', '/prospect/search', 301);
Route::get('/prospecting', [ProspectSearchController::class, 'index'])->name('prospecting');
Route::post('/prospecting/search', [ProspectSearchController::class, 'searchPeople'])->name('prospecting.search');
Route::post('/prospecting/lead', [ProspectSearchController::class, 'storeLead'])->name('prospecting.lead');

Route::get('/register-for-prospects', function ()
{
    session()->put('url.intended', route('subscription.index').'#prospectos');

    return redirect()->route('register');
})->name('register.redirect-to-prospects');

Route::middleware([
    'auth',
    config('jetstream.auth_session'),
])->group(function ()
{
    Route::get('/registration/billing', [RegistrationBillingController::class, 'billing'])->name('registration.billing');
    Route::get('/registration/checkout/start', [RegistrationBillingController::class, 'startCheckout'])->name('registration.checkout.start');
    Route::get('/registration/onboarding/qr', [RegistrationBillingController::class, 'onboardingQr'])->name('registration.onboarding.qr');
    Route::get('/registration/onboarding/chat-link-qr.png', [RegistrationBillingController::class, 'onboardingChatLinkQrImage'])->name('registration.onboarding.chat-link-qr-image');
});

Route::redirect('/prospectflow', '/prospect/search');

// Auto-login with token route
Route::get('/login/token/{token}', [AuthController::class, 'loginWithToken'])->name('login.token');

// Demo: auto-login as admin of the demo team
Route::get('/demo', [AuthController::class, 'demoLogin'])->name('demo.login');

// SLA Acceptance Routes (public - no auth required, autologin handled in controller)
Route::get('/sla/accept/{token}', [SLAController::class, 'showAcceptance'])->name('sla.accept');
Route::post('/sla/accept/{token}', [SLAController::class, 'accept'])->name('sla.accept.store');

// Budget preview (public - no auth, access by token hash)
Route::get('/p/budget/{token}', [ProjectController::class, 'budgetPreview'])->name('project.budget-preview');

Route::get('/dashboard/analytics', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');
Route::get('/dashboard/collaborator', [CollaboratorController::class, 'dashboard'])->name('dashboard.collaborator')->middleware('auth');

// Adding routes for other dashboard types
Route::get('/dashboard/client', function ()
{
    // This view doesn't exist yet, so we'll redirect to collaborator for now
    return view('collaborator.dashboard');
})->name('dashboard.client')->middleware('auth');

Route::get('/dashboard/project', function ()
{
    // This view doesn't exist yet, so we'll redirect to collaborator for now
    return view('collaborator.dashboard');
})->name('dashboard.project')->middleware('auth');

// errors
Route::get('misc-not-authorized', function ()
{
    return view('content.pages.pages-misc-not-authorized');
})->name('403');

Route::get('misc-error', function ()
{
    return view('content.pages.pages-misc-error');
})->name('404');

Route::get('/error-without-team', function ()
{
    return view('error-without-team');
})->name('error-without-team');

Route::get('misc-under-maintenance', function ()
{
    return view('content.pages.pages-misc-under-maintenance');
})->name('under-maintenance');

Route::get('misc-comingsoon', function ()
{
    return view('content.pages.pages-misc-comingsoon');
})->name('comingsoon');

// Public template view (no auth) – for sharing view links from assistant/WhatsApp
Route::get('/template/public/{hashedId}', [TemplateController::class, 'showPublic'])->name('template.show-public');

// Authenticated routes
Route::middleware(['auth'])->group(function ()
{
    if (app()->isLocal())
    {
        Route::post('/dev/prod-read-database', function (\Illuminate\Http\Request $request)
        {
            if (! config('app.allow_prod_read_toggle'))
            {
                abort(404);
            }

            $request->session()->put('use_prod_read_database', $request->boolean('enabled'));

            return redirect()->back();
        })->name('dev.prod-read-database');
    }

    Route::get('/dashboard', function ()
    {
        return redirect()->route('dashboard');
    });

    Route::get('/profile/data', function (\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $team = $user->currentTeam;
        $roleInTeam = null;
        if ($team)
        {
            $membership = $user->teams()->where('team_id', $team->id)->first();
            $roleInTeam = $membership?->pivot?->role ?? null;
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone !== null ? (string) $user->phone : null,
            'profile_photo_url' => $user->profile_photo_url ?? null,
            'current_team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
            ] : null,
            'role_in_team' => $roleInTeam,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ]);
    })->name('profile.data');

    // Team Settings
    Route::get('/team/{team}/settings', [TeamSettingController::class, 'index'])->name('team-settings.index');
    Route::get('/team/{team}/settings/social', [TeamSocialConnectionController::class, 'index'])->name('team-settings.social');
    Route::get('/team/{team}/settings/business-config', [TeamSettingController::class, 'businessConfig'])->name('team-settings.business-config');
    Route::post('/team/{team}/settings/business-config/generate-summary', [TeamSettingController::class, 'generateBusinessSummary'])->name('team-settings.business-config.generate-summary');
    Route::get('/team/{team}/settings/{group?}', [TeamSettingController::class, 'edit'])->name('team-settings.edit');
    Route::put('/team/{team}/settings', [TeamSettingController::class, 'update'])->name('team-settings.update');
    Route::post('/team/{team}/settings/chat/seed-default-assistant-prompts', [TeamSettingController::class, 'seedDefaultAssistantFlowPrompts'])->name('team-settings.chat.seed-default-assistant-prompts');
    Route::post('/team/{team}/test-smtp', [TeamSettingController::class, 'testSmtpConnection'])->name('team-settings.test-smtp');
    Route::post('/team/{team}/test-imap', [TeamSettingController::class, 'testImapConnection'])->name('team-settings.test-imap');
    Route::post('/team/{team}/test-stripe', [TeamSettingController::class, 'testStripeConnection'])->name('team-settings.test-stripe');
    Route::get('/integrations/google/connect', [GoogleIntegrationController::class, 'connect'])->name('integrations.google.connect');
    Route::get('/integrations/google/callback', [GoogleIntegrationController::class, 'callback'])->name('integrations.google.callback');
    Route::delete('/integrations/google/disconnect', [GoogleIntegrationController::class, 'disconnect'])->name('integrations.google.disconnect');
    Route::get('/integrations/google/synced-contacts', [GoogleSyncedPreviewController::class, 'contacts'])->name('integrations.google.synced-contacts');
    Route::post('/integrations/google/sync-contacts', [GoogleSyncedPreviewController::class, 'queueContactsSync'])->name('integrations.google.sync-contacts');
    Route::get('/integrations/google/synced-calendar', [GoogleSyncedPreviewController::class, 'calendar'])->name('integrations.google.synced-calendar');
    Route::post('/integrations/google/sync-calendar', [GoogleSyncedPreviewController::class, 'queueCalendarSync'])->name('integrations.google.sync-calendar');

    // Team Mailboxes (redirect for sidebar: /mailboxes -> current team mailboxes)
    Route::get('/mailboxes', function ()
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        return redirect()->route('team.mailboxes.index', $team);
    })->name('mailboxes.index');
    Route::get('/team/{team}/mailboxes', [TeamMailboxController::class, 'index'])->name('team.mailboxes.index');
    Route::get('/team/{team}/mailboxes/create', [TeamMailboxController::class, 'create'])->name('team.mailboxes.create');
    Route::post('/team/{team}/mailboxes', [TeamMailboxController::class, 'store'])->name('team.mailboxes.store');
    Route::get('/team/{team}/mailboxes/{mailbox}/edit', [TeamMailboxController::class, 'edit'])->name('team.mailboxes.edit');
    Route::put('/team/{team}/mailboxes/{mailbox}', [TeamMailboxController::class, 'update'])->name('team.mailboxes.update');
    Route::delete('/team/{team}/mailboxes/{mailbox}', [TeamMailboxController::class, 'destroy'])->name('team.mailboxes.destroy');
    Route::post('/team/{team}/mailboxes/{mailbox}/test-connection', [TeamMailboxController::class, 'testConnection'])->name('team.mailboxes.test-connection');
    Route::post('/team/{team}/test-twilio', [TeamSettingController::class, 'testTwilioConnection'])->name('team-settings.test-twilio');

    // Team Valorations
    Route::get('/team/{team}/valorations', [TeamSettingController::class, 'valorations'])->name('team-settings.valorations');
    Route::post('/team/{team}/valorations', [TeamSettingController::class, 'storeValoration'])->name('team-settings.valorations.store');
    Route::put('/team/{team}/valorations/{valoration}', [TeamSettingController::class, 'updateValoration'])->name('team-settings.valorations.update');
    Route::delete('/team/{team}/valorations/{valoration}', [TeamSettingController::class, 'destroyValoration'])->name('team-settings.valorations.destroy');

    // Team API Tokens
    Route::get('/team/{team}/api-tokens', [TeamSettingController::class, 'apiTokens'])->name('team-settings.api-tokens');
    Route::get('/team/{team}/passwords-settings', [TeamSettingController::class, 'passwords'])->name('team-settings.passwords');
    Route::post('/team/{team}/api-tokens/generate', [TeamSettingController::class, 'generateApiToken'])->name('team-settings.generate-api-token');
    Route::put('/team/{team}/passwords-settings', [TeamSettingController::class, 'updatePasswordsMasterKey'])->name('team-settings.passwords.update');
    Route::put('/team/{team}/api-tokens', [TeamSettingController::class, 'updateApiToken'])->name('team-settings.update-api-token');
    Route::post('/team/{team}/api-tokens/reveal', [TeamSettingController::class, 'revealApiToken'])->name('team-settings.reveal-api-token');
    Route::delete('/team/{team}/api-tokens/revoke', [TeamSettingController::class, 'revokeApiToken'])->name('team-settings.revoke-api-token');

    // Custom Translations
    Route::get('/team/{team}/custom-translations', [TeamSettingController::class, 'customTranslations'])->name('team-settings.custom-translations');
    Route::post('/team/{team}/custom-translations', [TeamSettingController::class, 'storeCustomTranslation'])->name('team-settings.custom-translations.store');
    Route::put('/team/{team}/custom-translations/{translation}', [TeamSettingController::class, 'updateCustomTranslation'])->name('team-settings.custom-translations.update');
    Route::delete('/team/{team}/custom-translations/{translation}', [TeamSettingController::class, 'destroyCustomTranslation'])->name('team-settings.custom-translations.destroy');
    Route::post('/team/{team}/custom-translations/import', [TeamSettingController::class, 'importCustomTranslations'])->name('team-settings.custom-translations.import');

    // Team Shortcuts
    Route::get('/team/{team}/shortcuts', [TeamSettingController::class, 'shortcuts'])->name('team-settings.shortcuts');
    Route::post('/team/{team}/shortcuts', [TeamSettingController::class, 'storeShortcuts'])->name('team-settings.shortcuts.store');

    // Team Passwords (Keychain)
    Route::get('/password/unlock', [TeamPasswordController::class, 'unlockForm'])->name('passwords.unlock.form');
    Route::post('/password/unlock', [TeamPasswordController::class, 'unlock'])->name('passwords.unlock');
    Route::post('/password/lock', [TeamPasswordController::class, 'lock'])->name('passwords.lock');
    Route::get('/password/list', [TeamPasswordController::class, 'index'])->name('passwords.index');
    Route::get('/password/create', [TeamPasswordController::class, 'create'])->name('passwords.create');
    Route::post('/password', [TeamPasswordController::class, 'store'])->name('passwords.store');
    Route::get('/password/{team_password}/edit', [TeamPasswordController::class, 'edit'])->name('passwords.edit');
    Route::put('/password/{team_password}', [TeamPasswordController::class, 'update'])->name('passwords.update');
    Route::delete('/password/{team_password}', [TeamPasswordController::class, 'destroy'])->name('passwords.destroy');
    Route::post('/password/{team_password}/reveal', [TeamPasswordController::class, 'reveal'])->name('passwords.reveal');
    Route::post('/password/{team_password}/share', [TeamPasswordController::class, 'createShare'])->name('passwords.share');

    // Confirm team invitation (when email did not arrive)
    Route::post('/teams/invitations/{invitation}/confirm', TeamInvitationConfirmController::class)
        ->name('teams.invitations.confirm');

    // Categories Management
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('/categories/quick-store', [CategoryController::class, 'quickStore'])->name('categories.quick-store');
    Route::get('/categories/module-options', [CategoryController::class, 'moduleOptions'])->name('categories.module-options');
    Route::get('/categories/{id}/duplicate', [CategoryController::class, 'duplicate'])->name('categories.duplicate');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{id}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
    Route::post('/categories/order', [CategoryController::class, 'updateOrder'])->name('categories.order');
    Route::get('/categories/{id}/items', [CategoryController::class, 'showItems'])->name('categories.items');

    // User Management (Admin only)
    Route::middleware('role:admin')->group(function ()
    {
        Route::get('/user-management', [UserManagement::class, 'UserManagement'])->name('user-management');
        Route::resource('/user-list', UserManagement::class);
    });

    // Account Management (Root only)
    Route::middleware(['role:root'])->group(function ()
    {
        Route::get('/account-management', [AccountController::class, 'index'])->name('account-management');
        Route::get('/account-management/{id}/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account-management/{id}', [AccountController::class, 'update'])->name('account.update');
        Route::post('/account-management', [AccountController::class, 'store'])->name('account.store');
        Route::get('/account-management/{id}/subscriptions', [AccountController::class, 'showSubscriptions'])->name('account.subscriptions');
        Route::get('/account-management/subscriptions/all', [AccountController::class, 'allSubscriptions'])->name('account.subscriptions.all');
        Route::post('/account-management/{id}/revoke-autologin', [AccountController::class, 'revokeAutologinToken'])->name('account.revoke-autologin');
        Route::post('/account-management/{id}/send-autologin-invitation', [AccountController::class, 'sendAutologinInvitation'])->name('account.send-autologin-invitation');

        // Product Management (Root only)
        Route::get('/account-management/products', [ProductManagementController::class, 'index'])->name('account.products.index');
        Route::get('/account-management/products/{id}/edit', [ProductManagementController::class, 'edit'])->name('account.products.edit');
        Route::put('/account-management/products/{id}', [ProductManagementController::class, 'update'])->name('account.products.update');
        Route::put('/account-management/products/{id}/update-and-sync', [ProductManagementController::class, 'updateAndSync'])->name('account.products.update-and-sync');
        Route::post('/account-management/products/{id}/sync', [ProductManagementController::class, 'sync'])->name('account.products.sync');
    });

    // Email Plans Management (Admin only)
    Route::middleware(['role:admin'])->group(function ()
    {
        Route::get('/email-plans', [EmailPlanController::class, 'index'])->name('email-plans.index');
        Route::post('/email-plans/{team}/assign', [EmailPlanController::class, 'assign'])->name('email-plans.assign');
        Route::get('/email-plans/{team}/details', [EmailPlanController::class, 'show'])->name('email-plans.show');

        // Email Plans Management Interface
        Route::get('/email-plans-management', [EmailPlansManagementController::class, 'index'])->name('email-plans-management.index');
        Route::post('/email-plans-management/{team}/assign', [EmailPlansManagementController::class, 'assign'])->name('email-plans-management.assign');
        Route::get('/email-plans-management/{team}/details', [EmailPlansManagementController::class, 'show'])->name('email-plans-management.show');
        Route::post('/email-plans-management/{team}/sync-usage', [EmailPlansManagementController::class, 'syncUsage'])->name('email-plans-management.sync-usage');
    });

    // Current team email plan (for all users)
    Route::get('/my-team/email-plan', [EmailPlanController::class, 'current'])->name('email-plans.current');

    // Contacts
    Route::get('/contact/search', action: [contactController::class, 'search'])->name('contact.search');
    Route::get('/contact/list', [contactController::class, 'index'])->name('contact-list');
    Route::get('/prospect/search', [ApolloController::class, 'index'])->name('prospect.search');
    Route::get('/contact/apollo', [ApolloController::class, 'index'])->name('contact.apollo');
    Route::post('/contact/apollo/people', [ApolloController::class, 'searchPeople'])->name('contact.apollo.people');
    Route::post('/contact/apollo/organizations', [ApolloController::class, 'searchOrganizations'])->name('contact.apollo.organizations');
    Route::post('/contact/apollo/add-person', [ApolloController::class, 'addPersonAsContact'])->name('contact.apollo.add-person');
    Route::post('/contact/end-action/{id}', [contactController::class, 'endAction'])->name('contact.end-action');
    Route::post('/contact/upload-file', [contactController::class, 'UploadFile'])->name('contact.upload-file');
    Route::get('/contact/import', [ContactController::class, 'showImportForm'])->name('contact.import');
    Route::get('/contacts/import-mapping', action: [ContactController::class, 'importMapping'])->name('contact.import-mapping');
    Route::post('/contact/upload-file-mapping', [ContactController::class, 'uploadFileForMapping'])->name('contact.upload-file-mapping');
    Route::post('/contact/process-mapping', [ContactController::class, 'processMapping'])->name('contact.process-mapping');
    Route::get('/contact/create', [contactController::class, 'create'])->name('contact.create');
    Route::get('/contact/{id}', [contactController::class, 'show'])->name('contact.show');
    Route::get('/contact/{id}/edit', [contactController::class, 'edit'])->name('contact.edit');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::put('/contact/{id}', [ContactController::class, 'update'])->name('contact.update');
    Route::delete('/contact/{id}', [contactController::class, 'destroy'])->name('contact.destroy');
    Route::post('/contact/{id}/update-sentiment', [contactController::class, 'updateSentiment'])->name('contact.update-sentiment');
    Route::post('/contact/{id}/update-astral-data', [contactController::class, 'updateAstralData'])->name('contact.update-astral-data');
    Route::patch('/contact/{id}/notes', [ContactController::class, 'updateNotes'])->name('contact.update-notes');
    Route::post('/contact/{id}/interactions', [ContactInteractionController::class, 'store'])->name('contact.interactions.store');

    Route::post('/contact/{id}/link-user', [ContactController::class, 'linkUser'])->name('contact.link-user');
    Route::post('/contact/{id}/unlink-user', [ContactController::class, 'unlinkUser'])->name('contact.unlink-user');
    Route::post('/contact/{id}/create-and-link-user', [ContactController::class, 'createAndLinkUser'])->name('contact.create-and-link-user');
    Route::post('/contact/{id}/set-current-enterprise', [ContactController::class, 'setCurrentEnterprise'])->name('contact.set-current-enterprise');

    Route::post('/delivery/{deliveryId}/resend', [ContactController::class, 'resendDelivery'])->name('delivery.resend');

    // Collaborators
    Route::get('/collaborator/list', [CollaboratorController::class, 'index'])->name('collaborator-list');
    Route::get('/collaborator/create', [CollaboratorController::class, 'create'])->name('collaborator.create');
    Route::post('/collaborator', [CollaboratorController::class, 'store'])->name('collaborator.store');
    Route::get('/collaborator/{id}', [CollaboratorController::class, 'show'])->name('collaborator.show');
    Route::get('/collaborator/{id}/edit', [CollaboratorController::class, 'edit'])->name('collaborator.edit');
    Route::put('/collaborator/{id}', [CollaboratorController::class, 'update'])->name('collaborator.update');
    Route::delete('/collaborator/{id}', [CollaboratorController::class, 'destroy'])->name('collaborator.destroy');
    Route::post('/collaborator/{id}/mark-as-watch', [CollaboratorController::class, 'markAsWatch'])->name('collaborator.markAsWatch');
    Route::post('/collaborator/{id}/send-to-blacklist', [CollaboratorController::class, 'sendToBlacklist'])->name('collaborator.sendToBlacklist');
    Route::post('/collaborator/{id}/send-notification', [CollaboratorController::class, 'sendNotification'])->name('collaborator.sendNotification');
    Route::post('/collaborator/{id}/update-software', [CollaboratorController::class, 'updateSoftware'])->name('collaborator.updateSoftware');
    Route::post('/collaborator/{id}/update-services', [CollaboratorController::class, 'updateServices'])->name('collaborator.updateServices');
    Route::post('/collaborator/{id}/update-topics', [CollaboratorController::class, 'updateTopics'])->name('collaborator.updateTopics');
    Route::post('/collaborator/{id}/update-valoration', [CollaboratorController::class, 'updateValoration'])->name('collaborator.updateValoration');
    Route::post('/collaborator/{id}/link-user', [CollaboratorController::class, 'linkUser'])->name('collaborator.link-user');
    Route::post('/collaborator/{id}/unlink-user', [CollaboratorController::class, 'unlinkUser'])->name('collaborator.unlink-user');
    Route::post('/collaborator/{id}/create-and-link-user', [CollaboratorController::class, 'createAndLinkUser'])->name('collaborator.create-and-link-user');
    Route::post('/collaborator/{id}/portfolio', [CollaboratorController::class, 'storePortfolio'])->name('collaborator.portfolio.store');
    Route::put('/collaborator/{id}/portfolio/{portfolioId}', [CollaboratorController::class, 'updatePortfolio'])->name('collaborator.portfolio.update');
    Route::delete('/collaborator/{id}/portfolio/{portfolioId}', [CollaboratorController::class, 'destroyPortfolio'])->name('collaborator.portfolio.destroy');
    Route::get('/collaborator/{id}/rates', [UserFareController::class, 'collaboratorRates'])->name('collaborator.rates');
    Route::post('/collaborator/{id}/rates', [UserFareController::class, 'saveCollaboratorRates'])->name('collaborator.rates.save');
    Route::get('/collaborator/{id}/rates/get', [UserFareController::class, 'getCollaboratorRates'])->name('collaborator.rates.get');
    Route::get('/collaborator/{id}/absences', [App\Http\Controllers\CollaboratorAvailabilityController::class, 'index'])->name('collaborator.absences');
    Route::post('/collaborator/{id}/absences/toggle-date', [App\Http\Controllers\CollaboratorAvailabilityController::class, 'toggleDate'])->name('collaborator.absences.toggle-date');
    Route::post('/collaborator/{id}/absences/update-weekly', [App\Http\Controllers\CollaboratorAvailabilityController::class, 'updateWeekly'])->name('collaborator.absences.update-weekly');

    // Additional collaborator routes from mailer branch
    Route::get('/collaborator/{id}/notifications', [CollaboratorController::class, 'notifications'])->name('collaborator.notifications');
    Route::get('/collaborator/{id}/activity', [CollaboratorController::class, 'activity'])->name('collaborator.activity');
    Route::get('/collaborator/{id}/media', [CollaboratorController::class, 'media'])->name('collaborator.media');
    Route::post('/collaborator/{id}/media', [CollaboratorController::class, 'uploadMedia'])->name('collaborator.media.upload');
    Route::put('/collaborator/{id}/media/{mediaId}', [CollaboratorController::class, 'updateMedia'])->name('collaborator.media.update');
    Route::delete('/collaborator/{id}/media/{mediaId}', [CollaboratorController::class, 'destroyMedia'])->name('collaborator.media.destroy');
    Route::get('/collaborator/{id}/accept', [CollaboratorController::class, 'showAcceptForm'])->name('collaborator.accept');
    Route::post('/collaborator/{id}/accept', [CollaboratorController::class, 'processAccept'])->name('collaborator.process-accept');

    // Employees
    Route::get('/employee/list', [EmployeeController::class, 'index'])->name('employee.index');
    Route::get('/employee/create', [EmployeeController::class, 'create'])->name('employee.create');
    Route::post('/employee', [EmployeeController::class, 'store'])->name('employee.store');
    Route::get('/employee/{id}', [EmployeeController::class, 'show'])->name('employee.show');
    Route::get('/employee/{id}/edit', [EmployeeController::class, 'edit'])->name('employee.edit');
    Route::put('/employee/{id}', [EmployeeController::class, 'update'])->name('employee.update');
    Route::delete('/employee/{id}', [EmployeeController::class, 'destroy'])->name('employee.destroy');
    Route::get('/employee/{id}/absences', [EmployeeController::class, 'absences'])->name('employee.absences');
    Route::post('/employee/{id}/absences/toggle-date', [EmployeeController::class, 'toggleAbsenceDate'])->name('employee.absences.toggle-date');
    Route::post('/employee/{id}/absences/update-weekly', [EmployeeController::class, 'updateWeeklyAvailability'])->name('employee.absences.update-weekly');
    Route::get('/employee/{id}/activity', [EmployeeController::class, 'activity'])->name('employee.activity');

    // Clients
    Route::get('/client/list', [ClientController::class, 'index'])->name('client-list');
    Route::post('/client/end-action/{id}', [ClientController::class, 'endAction'])->name('client.end-action');
    Route::get('/client/import', [ClientController::class, 'showImportForm'])->name('client.import');
    Route::post('/client/import-excel', [ClientController::class, 'importExcel'])->name('client.import-excel');

    Route::get('/client/create', [ClientController::class, 'create'])->name('client.create');
    Route::get('/client/{id}', [ClientController::class, 'show'])->name('client.show');
    Route::get('/empresas/{id}', [ClientController::class, 'show'])->name('empresas.show');
    Route::get('/client/{id}/linkable-contacts', [ClientController::class, 'linkableContacts'])->name('client.linkable-contacts');
    Route::post('/client/{id}/attach-contact', [ClientController::class, 'attachContact'])->name('client.attach-contact');
    Route::post('/client/{id}/detach-contact', [ClientController::class, 'detachContact'])->name('client.detach-contact');
    Route::get('/client/{id}/edit', [ClientController::class, 'edit'])->name('client.edit');
    Route::post('/client', [ClientController::class, 'store'])->name('client.store');
    Route::put('/client/{id}', [ClientController::class, 'update'])->name('client.update');
    Route::delete('/client/{id}', [ClientController::class, 'destroy'])->name('client.destroy');

    // Enterprises
    Route::get('/enterprise/list', [\App\Http\Controllers\EnterpriseController::class, 'index'])->name('enterprise.index');

    // Google Places (business search for enterprise/client)
    Route::get('/places/search', [GooglePlacesController::class, 'search'])->name('places.search');
    Route::get('/places/details/{placeId}', [GooglePlacesController::class, 'placeDetails'])->name('places.details')->where('placeId', '[^/]+');
    Route::post('/places/use-for-client', [GooglePlacesController::class, 'useForClient'])->name('places.use-for-client');

    // List60
    Route::get('/list60/list', [List60Controller::class, 'index'])->name('list60-list');
    Route::post('/list60', [List60Controller::class, 'store'])->name('list60.store');
    Route::put('/list60/{id}', [List60Controller::class, 'update'])->name('list60.update');
    Route::delete('/list60/{id}', [List60Controller::class, 'destroy'])->name('list60.destroy');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/whatsapp-status', [ChatController::class, 'whatsappStatus'])->name('chat.whatsapp-status');
    Route::get('/chat/whatsapp-qr-image', [ChatController::class, 'whatsappQrImage'])->name('chat.whatsapp-qr-image');
    Route::post('/chat/whatsapp-refresh-qr', [ChatController::class, 'whatsappRefreshQr'])->name('chat.whatsapp-refresh-qr');
    Route::post('/chat/whatsapp-disconnect', [ChatController::class, 'whatsappDisconnect'])->name('chat.whatsapp-disconnect');
    Route::post('/chat/link-current-number', [ChatController::class, 'linkCurrentNumberFromService'])->name('chat.link-current-number');
    Route::get('/chat/messages/{phone}', [ChatController::class, 'getMessages'])->name('chat.messages');
    Route::get('/chat/list', [ChatController::class, 'getChatList'])->name('chat.list');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/assistant-history', [ChatController::class, 'assistantHistory'])->name('chat.assistant-history');
    Route::patch('/chat/ai-toggle-preference', [ChatController::class, 'updateAiTogglePreference'])->name('chat.ai-toggle-preference');
    Route::patch('/chat/team-settings-sidebar', [ChatController::class, 'updateChatTeamSettingsSidebar'])->name('chat.team-settings-sidebar');
    Route::patch('/chat/assistant-auto-respond', [ChatController::class, 'updateAssistantAutoRespond'])->name('chat.assistant-auto-respond');
    Route::patch('/chat/notification-preference', [ChatController::class, 'updateNotificationPreference'])->name('chat.notification-preference');
    Route::post('/chat/assistant', [ChatController::class, 'assistant'])->name('chat.assistant');
    Route::post('/chat/send-template', [ChatController::class, 'sendTemplateMessage'])->name('chat.send-template');

    // Chatbot (Livewire assistant with general router + flows)
    Route::get('/chatbot', fn () => view('chatbot'))->name('chatbot');

    // Users
    Route::get('/user/list', [UserController::class, 'index'])->name('user.index');
    Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
    Route::post('/user', [UserController::class, 'store'])->name('user.store');
    Route::get('/user/{user}', [UserController::class, 'show'])->name('user.show');
    Route::get('/user/{user}/edit', [UserController::class, 'edit'])->name('user.edit');
    Route::put('/user/{user}', [UserController::class, 'update'])->name('user.update');
    Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy');

    // Mail
    Route::get('/mail/list', [MailController::class, 'index'])->name('mail-list');
    Route::post('/mail/compose/suggest', [MailController::class, 'suggestComposeBody'])->name('mail.compose-suggest');
    Route::get('/mail/sync', [MailController::class, 'sync'])->name('mail-sync');

    // Services
    Route::get('/service/list', [ServiceController::class, 'index'])->name('service-list');
    Route::get('/service/projection', [ServiceController::class, 'projectBilling'])->name('service.projectBilling');
    Route::get('/service/create', [ServiceController::class, 'create'])->name('service.create');
    Route::get('/service/{id}', [ServiceController::class, 'show'])->name('service.show');
    Route::get('/service/{id}/edit', [ServiceController::class, 'edit'])->name('service.edit');
    Route::post('/service', [ServiceController::class, 'store'])->name('service.store');
    Route::put('/service/{id}', [ServiceController::class, 'update'])->name('service.update');
    Route::delete('/service/{id}', [ServiceController::class, 'destroy'])->name('service.destroy');

    // SLA Routes (for subscription products) - Management routes require auth
    Route::get('/product/{productId}/sla/create', [SLAController::class, 'create'])->name('sla.create');
    Route::post('/product/{productId}/sla', [SLAController::class, 'store'])->name('sla.store');
    Route::get('/product/{productId}/sla/{slaId}/edit', [SLAController::class, 'edit'])->name('sla.edit');
    Route::put('/product/{productId}/sla/{slaId}', [SLAController::class, 'update'])->name('sla.update');
    Route::post('/product/{productId}/send-sla', [SLAController::class, 'sendSLA'])->name('sla.send');

    // Projects - IMPORTANT: Specific routes MUST be before parameterized routes
    Route::get('/project/list', [ProjectController::class, 'index'])->name('project-list');
    Route::get('/project/create', [ProjectController::class, 'create'])->name('project.create');
    Route::post('/project/generate-budget-spec', [ProjectController::class, 'generateBudgetSpec'])->name('project.generate-budget-spec');
    Route::post('/project', [ProjectController::class, 'store'])->name('project.store');
    Route::get('/project/{id}', [ProjectController::class, 'show'])->name('project.show');
    Route::post('/project/{id}/add-suggested-task', [ProjectController::class, 'addSuggestedTask'])->name('project.add-suggested-task');
    Route::get('/project/{id}/edit', [ProjectController::class, 'edit'])->name('project.edit');
    Route::put('/project/{id}', [ProjectController::class, 'update'])->name('project.update');
    Route::delete('/project/{id}', [ProjectController::class, 'destroy'])->name('project.destroy');
    Route::get('/opportunity/list', [OpportunityController::class, 'index'])->name('opportunity.index');
    Route::get('/opportunity/create', [OpportunityController::class, 'create'])->name('opportunity.create');
    Route::post('/opportunity', [OpportunityController::class, 'store'])->name('opportunity.store');
    Route::get('/opportunity/{id}', [OpportunityController::class, 'show'])->name('opportunity.show');
    Route::get('/opportunity/{id}/edit', [OpportunityController::class, 'edit'])->name('opportunity.edit');
    Route::put('/opportunity/{id}', [OpportunityController::class, 'update'])->name('opportunity.update');
    Route::delete('/opportunity/{id}', [OpportunityController::class, 'destroy'])->name('opportunity.destroy');

    Route::get('/project/{id}/select-collaborators', [ProjectController::class, 'selectCollaborators'])->name('project.select-collaborators');
    Route::post('/project/{id}/filter-collaborators', [ProjectController::class, 'filterCollaborators'])->name('project.filter-collaborators');
    Route::post('/project/{id}/send-notifications', [ProjectController::class, 'sendCollaboratorNotifications'])->name('project.send-notifications');
    Route::delete('/project/{project}/remove-collaborator/{collaborator}', [ProjectController::class, 'removeCollaborator'])->name('project.remove-collaborator');
    Route::get('/project/{project}/add-services', [ProjectController::class, 'addServices'])->name('project.add-services');
    Route::post('/project/{project}/store-services', [ProjectController::class, 'storeServices'])->name('project.store-services');

    // Project services modal routes
    Route::get('/project/{project}/services', [ProjectController::class, 'getServices'])->name('project.get-services');
    Route::post('/project/{project}/service', [ProjectController::class, 'storeService'])->name('project.store-service');
    Route::put('/project/{project}/service/{serviceId}', [ProjectController::class, 'updateService'])->name('project.update-service');
    Route::delete('/project/{project}/service/{serviceId}', [ProjectController::class, 'deleteService'])->name('project.delete-service');

    // Time Tracking Routes
    Route::get('/time/list', [TimeController::class, 'index'])->name('time.index');
    Route::get('/time/timer', [TimeController::class, 'timer'])->name('time.timer');
    Route::get('/time/create', [TimeController::class, 'create'])->name('time.create');
    Route::post('/time', [TimeController::class, 'store'])->name('time.store');
    Route::get('/time/{id}/edit', [TimeController::class, 'edit'])->name('time.edit');
    Route::put('/time/{id}', [TimeController::class, 'update'])->name('time.update');
    Route::delete('/time/{id}', [TimeController::class, 'destroy'])->name('time.destroy');
    Route::post('/time/start', [TimeController::class, 'start'])->name('time.start');
    Route::post('/time/{id}/stop', [TimeController::class, 'stop'])->name('time.stop');
    Route::get('/time/running', [TimeController::class, 'running'])->name('time.running');
    Route::get('/time/tasks', [TimeController::class, 'getTasks'])->name('time.tasks');

    // Team users (for internal selects)
    Route::get('/api/team-users', function ()
    {
        $rolesParam = request('roles');
        $roleNames = $rolesParam ? array_filter(explode(',', $rolesParam)) : ['admin', 'collaborator', 'employee'];

        $users = \App\Models\User::query()
            ->whereHas('teams', function ($q)
            {
                $q->where('team_id', auth()->user()->currentTeam->id);
            })
            ->whereHas('roles', function ($q) use ($roleNames)
            {
                $q->whereIn('name', $roleNames);
            })
            ->with(['roles' => function ($q)
            {
                $q->select('name');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($u)
            {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->roles->first()->name ?? null,
                ];
            })
            ->values();

        return response()->json(['users' => $users]);
    })->name('api.team-users');

    // Attendance Routes (global in/out)
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/start', [AttendanceController::class, 'start'])->name('attendance.start');
    Route::post('/attendance/{id}/pause', [AttendanceController::class, 'pause'])->name('attendance.pause');
    Route::post('/attendance/{id}/resume', [AttendanceController::class, 'resume'])->name('attendance.resume');
    Route::post('/attendance/{id}/stop', [AttendanceController::class, 'stop'])->name('attendance.stop');
    Route::get('/attendance/running', [AttendanceController::class, 'running'])->name('attendance.running');

    // Task Routes
    Route::get('/task/list', [TaskController::class, 'index'])->name('task.index');
    Route::get('/task/create', [TaskController::class, 'create'])->name('task.create');
    Route::get('/task/{id}', [TaskController::class, 'show'])->name('task.show');
    Route::get('/task/{id}/edit', [TaskController::class, 'edit'])->name('task.edit');
    Route::post('/task', [TaskController::class, 'store'])->name('task.store');
    Route::put('/task/{id}', [TaskController::class, 'update'])->name('task.update');
    Route::delete('/task/{id}', [TaskController::class, 'destroy'])->name('task.destroy');
    Route::post('/task/update-status', [TaskController::class, 'updateStatus'])->name('task.update-status');
    Route::post('/task/update-order', [TaskController::class, 'updateOrder'])->name('task.update-order');
    Route::get('/task/{id}/activities', [TaskController::class, 'getActivities'])->name('task.activities');
    Route::post('/task/send-communication', [TaskController::class, 'sendCommunication'])->name('task.send-communication');
    Route::get('/task/{id}/communications', [TaskController::class, 'getCommunications'])->name('task.communications');
    Route::get('/task/{id}/total-time', [TaskController::class, 'getTotalTime'])->name('task.total-time');

    // Ticket Routes
    Route::get('/ticket/list', [TicketController::class, 'index'])->name('ticket.index');
    Route::get('/ticket/create', [TicketController::class, 'create'])->name('ticket.create');
    Route::post('/ticket', [TicketController::class, 'store'])->name('ticket.store');
    Route::get('/ticket/{id}', [TicketController::class, 'show'])->name('ticket.show');
    Route::post('/ticket/{id}/response', [TicketController::class, 'addResponse'])->name('ticket.response');
    Route::post('/ticket/{id}/status', [TicketController::class, 'updateStatus'])->name('ticket.status');
    Route::post('/ticket/{id}/assign', [TicketController::class, 'assign'])->name('ticket.assign');
    Route::post('/ticket/{id}/priority', [TicketController::class, 'updatePriority'])->name('ticket.priority');
    Route::post('/ticket/{id}/close', [TicketController::class, 'close'])->name('ticket.close');
    Route::post('/ticket/{id}/rate', [TicketController::class, 'rate'])->name('ticket.rate');
    Route::get('/ticket/{id}/attachment/{media}', [TicketController::class, 'downloadAttachment'])->name('ticket.attachment');
    Route::delete('/ticket/{id}', [TicketController::class, 'destroy'])->name('ticket.destroy');

    // Multimedia Routes
    Route::get('/multimedia/list', [MultimediaController::class, 'index'])->name('multimedia.index');
    Route::get('/multimedia/create', [MultimediaController::class, 'create'])->name('multimedia.create');
    Route::post('/multimedia', [MultimediaController::class, 'store'])->name('multimedia.store');
    Route::get('/multimedia/{multimedia}/edit', [MultimediaController::class, 'edit'])->name('multimedia.edit');
    Route::put('/multimedia/{multimedia}', [MultimediaController::class, 'update'])->name('multimedia.update');
    Route::delete('/multimedia/{multimedia}', [MultimediaController::class, 'destroy'])->name('multimedia.destroy');
    Route::get('/multimedia/gallery/{tag}', [MultimediaController::class, 'gallery'])->name('multimedia.gallery');
    Route::post('/multimedia/gallery/order', [MultimediaController::class, 'updateGalleryOrder'])->name('multimedia.gallery.order');
    Route::get('/tags/search', [MultimediaController::class, 'searchTags'])->name('tags.search');

    // Team files (company documents & brand assets)
    Route::get('/team-file/list', [TeamFileController::class, 'index'])->name('team-file.index');
    Route::get('/team-file/create', [TeamFileController::class, 'create'])->name('team-file.create');
    Route::post('/team-file', [TeamFileController::class, 'store'])->name('team-file.store');
    Route::get('/team-file/{team_file}', [TeamFileController::class, 'show'])->name('team-file.show');
    Route::get('/team-file/{team_file}/edit', [TeamFileController::class, 'edit'])->name('team-file.edit');
    Route::put('/team-file/{team_file}', [TeamFileController::class, 'update'])->name('team-file.update');
    Route::delete('/team-file/{team_file}', [TeamFileController::class, 'destroy'])->name('team-file.destroy');
    Route::get('/team-file/{team_file}/download', [TeamFileController::class, 'download'])->name('team-file.download');
    Route::post('/team-file/{team_file}/restore-version/{history}', [TeamFileController::class, 'restoreVersion'])->name('team-file.restore-version');

    // Contents Routes
    Route::get('/contents', [ContentController::class, 'index'])->name('contents.index');
    Route::get('/contents/create', [ContentController::class, 'create'])->name('contents.create');
    Route::post('/contents', [ContentController::class, 'store'])->name('contents.store');
    Route::get('/contents/{content}', [ContentController::class, 'show'])->name('contents.show');
    Route::get('/contents/{content}/edit', [ContentController::class, 'edit'])->name('contents.edit');
    Route::put('/contents/{content}', [ContentController::class, 'update'])->name('contents.update');
    Route::delete('/contents/{content}', [ContentController::class, 'destroy'])->name('contents.destroy');
    Route::post('/contents/order', [ContentController::class, 'updateOrder'])->name('contents.order');

    // Content Field Configs Routes
    Route::get('/content-field-configs', [ContentFieldConfigController::class, 'index'])->name('content-field-configs.index');
    Route::get('/content-field-configs/create', [ContentFieldConfigController::class, 'create'])->name('content-field-configs.create');
    Route::post('/content-field-configs', [ContentFieldConfigController::class, 'store'])->name('content-field-configs.store');
    Route::get('/content-field-configs/{content_field_config}', [ContentFieldConfigController::class, 'show'])->name('content-field-configs.show');
    Route::get('/content-field-configs/{content_field_config}/edit', [ContentFieldConfigController::class, 'edit'])->name('content-field-configs.edit');
    Route::put('/content-field-configs/{content_field_config}', [ContentFieldConfigController::class, 'update'])->name('content-field-configs.update');
    Route::delete('/content-field-configs/{content_field_config}', [ContentFieldConfigController::class, 'destroy'])->name('content-field-configs.destroy');

    // Public routes for client responses (no auth required)
    Route::get('/task-communication/{token}', [TaskController::class, 'showCommunicationResponse'])
        ->name('task.communication.respond')
        ->withoutMiddleware(['auth']);
    Route::post('/task-communication/{token}', [TaskController::class, 'storeCommunicationResponse'])
        ->name('task.communication.respond.store')
        ->withoutMiddleware(['auth']);

    // Task Board Routes
    Route::get('/task-board', [App\Http\Controllers\TaskBoardController::class, 'index'])->name('task-board.index');
    Route::get('/task-board/create', [App\Http\Controllers\TaskBoardController::class, 'create'])->name('task-board.create');
    Route::post('/task-board', [App\Http\Controllers\TaskBoardController::class, 'store'])->name('task-board.store');
    Route::get('/task-board/{id}/edit', [App\Http\Controllers\TaskBoardController::class, 'edit'])->name('task-board.edit');
    Route::get('/task-board/{id}/destroy', [App\Http\Controllers\TaskBoardController::class, 'destroy'])->name('task-board.destroy');
    Route::post('/task-board/update-order', [App\Http\Controllers\TaskBoardController::class, 'updateOrder'])->name('task-board.update-order');

    // Product Routes
    Route::get('/product/list', [ProductController::class, 'index'])->name('product.index');
    Route::get('/product/create', [ProductController::class, 'create'])->name('product.create');
    Route::get('/product/{id}', [ProductController::class, 'show'])->name('product.show');
    Route::get('/product/{id}/edit', [ProductController::class, 'edit'])->name('product.edit');
    Route::post('/product', [ProductController::class, 'store'])->name('product.store');
    Route::put('/product/{id}', [ProductController::class, 'update'])->name('product.update');
    Route::delete('/product/{id}', [ProductController::class, 'destroy'])->name('product.destroy');

    // Store Routes
    Route::get('/store/list', [StoreController::class, 'index'])->name('store.index');
    Route::get('/store/create', [StoreController::class, 'create'])->name('store.create');
    Route::post('/store', [StoreController::class, 'store'])->name('store.store');
    Route::get('/store/{id}', [StoreController::class, 'show'])->name('store.show');
    Route::get('/store/{id}/edit', [StoreController::class, 'edit'])->name('store.edit');
    Route::put('/store/{id}', [StoreController::class, 'update'])->name('store.update');
    Route::delete('/store/{id}', [StoreController::class, 'destroy'])->name('store.destroy');

    // WordPress (posts & pages) - content from WordPress site
    Route::post('/wordpress/sync', [App\Http\Controllers\WordPressController::class, 'sync'])->name('wordpress.sync');
    Route::get('/wordpress/posts', [App\Http\Controllers\WordPressController::class, 'posts'])->name('wordpress.posts');
    Route::get('/wordpress/posts/{id}/edit', [App\Http\Controllers\WordPressController::class, 'editPost'])->name('wordpress.posts.edit');
    Route::put('/wordpress/posts/{id}', [App\Http\Controllers\WordPressController::class, 'updatePost'])->name('wordpress.posts.update');
    Route::get('/wordpress/pages', [App\Http\Controllers\WordPressController::class, 'pages'])->name('wordpress.pages');
    Route::get('/wordpress/pages/{id}/edit', [App\Http\Controllers\WordPressController::class, 'editPage'])->name('wordpress.pages.edit');
    Route::put('/wordpress/pages/{id}', [App\Http\Controllers\WordPressController::class, 'updatePage'])->name('wordpress.pages.update');

    // Order Routes
    Route::get('/order/list', [OrderController::class, 'index'])->name('order.index');
    Route::get('/order/create', [OrderController::class, 'create'])->name('order.create');
    Route::get('/order/{id}', [OrderController::class, 'show'])->name('order.show');
    Route::get('/order/{id}/edit', [OrderController::class, 'edit'])->name('order.edit');
    Route::post('/order', [OrderController::class, 'store'])->name('order.store');
    Route::put('/order/{id}', [OrderController::class, 'update'])->name('order.update');
    Route::delete('/order/{id}', [OrderController::class, 'destroy'])->name('order.destroy');

    // Invoice & Payment Routes
    Route::get('/invoice/list', [InvoiceController::class, 'index'])->name('invoice.index');
    Route::get('/invoices', function ()
    {
        return redirect()->route('invoice.index');
    });
    Route::get('/invoices/{invoice}/link-enterprise', [InvoiceController::class, 'linkEnterpriseForm'])->name('invoice.link-enterprise');
    Route::post('/invoices/{invoice}/link-enterprise', [InvoiceController::class, 'linkEnterprise'])->name('invoice.link-enterprise.store');
    Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('invoice.show');
    Route::get('/invoices/data', [InvoiceController::class, 'data'])->name('invoice.data');

    // Legacy invoice routes already defined above
    Route::prefix('invoice')->group(function ()
    {
        Route::get('/create', [App\Http\Controllers\apps\InvoiceAdd::class, 'index'])->name('invoice.create');

        Route::delete('/destroy/{id}', function ($id)
        {
            return redirect()->route('invoice.index');
        })->name('invoice.destroy');

        Route::get('/edit/{id}', [InvoiceController::class, 'show'])->name('invoice.edit');
    });

    // Payments (all transactions)
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}/link-invoice', [PaymentController::class, 'linkInvoiceForm'])->name('payments.link-invoice');
    Route::post('/payments/{payment}/link-invoice', [PaymentController::class, 'linkInvoice'])->name('payments.link-invoice.store');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('payments.show');

    // Income module
    Route::get('/income/list', [IncomeController::class, 'index'])->name('income.index');

    // Expense module
    Route::get('/expense/list', [ExpenseController::class, 'index'])->name('expense.index');

    // Financial Dashboard (Accounting)
    Route::get('/finance-dashboard', [FinancialDashboardController::class, 'index'])->name('finance-dashboard.index');

    Route::prefix('payment')->group(function ()
    {
        Route::get('/list', function ()
        {
            return redirect()->route('payments.index');
        })->name('payment.index');
    });

    // Hosting
    Route::resource('hosting', HostingController::class);
    Route::get('/hosting/data', [HostingController::class, 'data'])->name('hosting.data');

    // Domains
    Route::resource('domain', DomainController::class);
    Route::post('/domain/{domain}/refresh', [DomainController::class, 'refresh'])->name('domain.refresh');
    Route::post('/domain/{domain}/toggle-suspension', [DomainController::class, 'toggleSuspension'])->name('domain.toggle-suspension');

    // Servers
    Route::resource('server', ServerController::class);
    Route::post('/server/{server}/test-connection', [ServerController::class, 'testConnection'])->name('server.testConnection');

    // Custom Translations

    // Accounting Routes
    // NOTE: These routes are now handled by the humano-billing package
    // See: packages/humano-billing/routes/web.php

    // Campaigns (placeholder — mailer message list remains at message/list)
    Route::get('/campaigns', [CampaignsController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/templates/select', [CampaignsController::class, 'selectTemplate'])->name('campaigns.templates.select');
    Route::get('/campaigns/classic-editor', [CampaignsController::class, 'classicEditor'])->name('campaigns.classic-editor');
    Route::post('/campaigns/classic-editor', [CampaignsController::class, 'storeClassicEditor'])->name('campaigns.classic-editor.store');
    Route::get('/campaigns/classic-editor/grapes', [CampaignsController::class, 'classicEditorGrapes'])->name('campaigns.classic-editor.grapes');
    Route::get('/campaigns/{campaign}/edit', [CampaignsController::class, 'edit'])
        ->whereNumber('campaign')
        ->name('campaigns.edit');
    Route::get('/campaigns/{campaign}', [CampaignsController::class, 'show'])
        ->whereNumber('campaign')
        ->name('campaigns.show');
    Route::post('/campaigns/{campaign}/pause-messages', [CampaignsController::class, 'pauseMessages'])
        ->whereNumber('campaign')
        ->name('campaigns.pause-messages');
    Route::put('/campaigns/{campaign}', [CampaignsController::class, 'update'])
        ->whereNumber('campaign')
        ->name('campaigns.update');
    Route::patch('/campaigns/{campaign}/sequence', [CampaignsController::class, 'updateSequence'])
        ->whereNumber('campaign')
        ->name('campaigns.sequence.update');

    // Messages
    Route::get('message/list', [MessageController::class, 'index'])->name('message.index');
    Route::get('message/create', [MessageController::class, 'create'])->name('message.create');
    Route::get('message/template-email-preview', [MessageController::class, 'templateEmailPreviewForMessageForm'])->name('message.template-email-preview');
    Route::post('message/sync-template-html-open-editor', [MessageController::class, 'syncTemplateHtmlOpenVisualEditor'])->name('message.sync-template-html-open-editor');
    Route::get('message/{id}', [MessageController::class, 'show'])->name('message.show');
    Route::get('message/{id}/debug', [MessageController::class, 'debug'])->name('message.debug');  // Temporary debug route
    Route::get('message/{id}/edit', [MessageController::class, 'edit'])->name('message.edit');
    Route::get('message/{id}/preview', [MessageController::class, 'preview'])->name('message.preview');
    Route::get('message/{id}/preview-html', [MessageController::class, 'previewHtml'])->name('message.preview.html');
    Route::post('message/{id}/start', [MessageController::class, 'startCampaign'])->name('message.start');
    Route::post('message/{id}/pause', [MessageController::class, 'pauseCampaign'])->name('message.pause');
    Route::post('message/{id}/send-pending-now', [MessageController::class, 'sendPendingNow'])->name('message.send-pending-now');
    Route::post('message/test-from-template', [MessageController::class, 'testSendFromTemplate'])->name('message.test-from-template');
    Route::post('message/{id}/test', [MessageController::class, 'testSend'])->name('message.test');
    Route::post('message/delivery/{deliveryId}/resend', [MessageController::class, 'resendDelivery'])->name('message.delivery.resend');
    Route::get('message/{id}/link-details/{encodedLink}', [MessageController::class, 'getLinkDetails'])->name('message.link-details');
    Route::post('message', [MessageController::class, 'store'])->name('message.store');
    Route::put('message/{id}', [MessageController::class, 'update'])->name('message.update');
    Route::delete('message/{id}', [MessageController::class, 'destroy'])->name('message.destroy');

    Route::get('/send-sms', [MessageController::class, 'sendSmsMessage']);
    Route::get('/send-whatsapp', [MessageController::class, 'sendWhatsAppMessage']);
    Route::get('/send-email', [MessageController::class, 'sendSendGridMessage']);

    // Templates
    Route::post('/template/generate-html', [TemplateController::class, 'generateHtml'])->name('template.generate-html')->middleware('throttle:10,1');
    Route::get('/template/generate-html/result/{token}', [TemplateController::class, 'generateHtmlResult'])->name('template.generate-html.result');
    Route::get('/template/list', [TemplateController::class, 'index'])->name('template.index');
    Route::get('/template/create', [TemplateController::class, 'create'])->name('template.create');
    Route::get('/template/{hashedId}', [TemplateController::class, 'show'])->name('template.show');
    Route::get('/template/{hashedId}/edit', [TemplateController::class, 'edit'])->name('template.edit');
    Route::post('/template', [TemplateController::class, 'store'])->name('template.store');
    Route::post('/template/{hashedId}/duplicate', [TemplateController::class, 'duplicate'])->name('template.duplicate');
    Route::put('/template/{hashedId}', [TemplateController::class, 'update'])->name('template.update');
    Route::delete('/template/{hashedId}', [TemplateController::class, 'destroy'])->name('template.destroy');
    Route::get('/template/{hashedId}/editor', [TemplateController::class, 'editor'])->name('template.editor');
    Route::get('/template/view/{hashedId}', [TemplateController::class, 'show'])->name('template.view');

    // Fare Types
    Route::get('/fare', [FareController::class, 'index'])->name('fare.index');
    Route::get('/fare/create', [FareController::class, 'create'])->name('fare.create');
    Route::post('/fare', [FareController::class, 'store'])->name('fare.store');
    Route::get('/fare/{fare}', [FareController::class, 'show'])->name('fare.show');
    Route::get('/fare/{fare}/edit', [FareController::class, 'edit'])->name('fare.edit');
    Route::put('/fare/{fare}', [FareController::class, 'update'])->name('fare.update');
    Route::delete('/fare/{fare}', [FareController::class, 'destroy'])->name('fare.destroy');

    // Prompts (module_prompts; gated by team module "prompts")
    Route::get('/prompt/list', [PromptController::class, 'index'])->name('prompt-list');
    Route::get('/prompt/create', [PromptController::class, 'create'])->name('prompt.create');
    Route::post('/prompt', [PromptController::class, 'store'])->name('prompt.store');
    Route::get('/prompt/{prompt}/edit', [PromptController::class, 'edit'])->name('prompt.edit');
    Route::get('/prompt/{prompt}', [PromptController::class, 'show'])->name('prompt.show');
    Route::post('/prompt/{prompt}/preview', [PromptController::class, 'preview'])->name('prompt.preview');
    Route::put('/prompt/{prompt}', [PromptController::class, 'update'])->name('prompt.update');
    Route::delete('/prompt/{prompt}', [PromptController::class, 'destroy'])->name('prompt.destroy');

    // Software Management
    Route::get('/software', [SoftwareController::class, 'index'])->name('software.index');
    Route::get('/software/create', [SoftwareController::class, 'create'])->name('software.create');
    Route::post('/software', [SoftwareController::class, 'store'])->name('software.store');
    Route::get('/software/{software}/edit', [SoftwareController::class, 'edit'])->name('software.edit');
    Route::put('/software/{software}', [SoftwareController::class, 'update'])->name('software.update');
    Route::delete('/software/{software}', [SoftwareController::class, 'destroy'])->name('software.destroy');
    Route::get('/software/autocomplete', [SoftwareController::class, 'autocomplete'])->name('software.autocomplete');

    // Certification Management
    Route::get('/certification', [CertificationController::class, 'index'])->name('certification.index');
    Route::get('/certification/create', [CertificationController::class, 'create'])->name('certification.create');
    Route::post('/certification', [CertificationController::class, 'store'])->name('certification.store');
    Route::get('/certification/{certification}/edit', [CertificationController::class, 'edit'])->name('certification.edit');
    Route::put('/certification/{certification}', [CertificationController::class, 'update'])->name('certification.update');
    Route::delete('/certification/{certification}', [CertificationController::class, 'destroy'])->name('certification.destroy');

    // Style Book Management
    Route::get('/stylebook', [StylebookController::class, 'index'])->name('stylebook.index');
    Route::get('/stylebook/create', [StylebookController::class, 'create'])->name('stylebook.create');
    Route::post('/stylebook', [StylebookController::class, 'store'])->name('stylebook.store');
    Route::get('/stylebook/{stylebook}', [StylebookController::class, 'show'])->name('stylebook.show');
    Route::get('/stylebook/{stylebook}/edit', [StylebookController::class, 'edit'])->name('stylebook.edit');
    Route::put('/stylebook/{stylebook}', [StylebookController::class, 'update'])->name('stylebook.update');
    Route::delete('/stylebook/{stylebook}', [StylebookController::class, 'destroy'])->name('stylebook.destroy');

    // Notification Management
    Route::get('/notification/list', [NotificationController::class, 'index'])->name('notification-list');
    Route::get('/notification/create', [NotificationController::class, 'create'])->name('notification.create');
    Route::post('/notification', [NotificationController::class, 'store'])->name('notification.store');
    Route::get('/notification/{notification}', [NotificationController::class, 'show'])->name('notification.show');
    Route::get('/notification/{notification}/edit', [NotificationController::class, 'edit'])->name('notification.edit');
    Route::put('/notification/{notification}', [NotificationController::class, 'update'])->name('notification.update');
    Route::delete('/notification/{notification}', [NotificationController::class, 'destroy'])->name('notification.destroy');
    Route::post('/notification/{notification}/send', [NotificationController::class, 'send'])->name('notification.send');
    Route::post('/notification/{notification}/resend', [NotificationController::class, 'resend'])->name('notification.resend');
    Route::post('/notification/get-template', [NotificationController::class, 'getTemplate'])->name('notification.get-template');
    Route::post('/notification/bulk-send', [NotificationController::class, 'bulkSend'])->name('notification.bulk-send');
    Route::post('/notification/bulk-delete', [NotificationController::class, 'bulkDelete'])->name('notification.bulk-delete');

    // User Custom Fares
    Route::get('/user-fare', [UserFareController::class, 'index'])->name('user-fare.index');
    Route::get('/user-fare/create', [UserFareController::class, 'create'])->name('user-fare.create');
    Route::post('/user-fare', [UserFareController::class, 'store'])->name('user-fare.store');
    Route::get('/user-fare/{userFare}', [UserFareController::class, 'show'])->name('user-fare.show');
    Route::get('/user-fare/{userFare}/edit', [UserFareController::class, 'edit'])->name('user-fare.edit');
    Route::put('/user-fare/{userFare}', [UserFareController::class, 'update'])->name('user-fare.update');
    Route::delete('/user-fare/{userFare}', [UserFareController::class, 'destroy'])->name('user-fare.destroy');

    // Academy - Now using the humano-academy package
    // Route::get('/academy/list', [AcademyController::class, 'index'])->name('academy-list');
    // Route::get('/academy/{id}', [AcademyController::class, 'show'])->name('academy.show');

    // Subscription Management
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/subscription/stripe/{stripeSubscription}/link-client', [SubscriptionController::class, 'linkClientForm'])->name('subscription.stripe-link-client');
    Route::post('/subscription/stripe/{stripeSubscription}/link-client', [SubscriptionController::class, 'linkClient'])->name('subscription.stripe-link-client.store');
    Route::post('/subscription/sync', [SubscriptionController::class, 'syncFromStripe'])->name('subscription.sync');
    Route::get('/subscription/billing-info', [SubscriptionController::class, 'billingInfo'])->name('subscription.billing-info');
    Route::post('/subscription/save-billing-info', [SubscriptionController::class, 'saveBillingInfo'])->name('subscription.save-billing-info');
    Route::post('/subscription/validate-coupon', [SubscriptionController::class, 'validateCoupon'])->name('subscription.validate-coupon');
    Route::match(['get', 'post'], '/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
    Route::get('/subscription/prospection-success', [SubscriptionController::class, 'prospectionSuccess'])->name('subscription.prospection-success');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
    Route::post('/subscription/swap', [SubscriptionController::class, 'swap'])->name('subscription.swap');

    // Payment subscriptions (forms of payment for services)
    Route::get('/payment-subscription', [PaymentSubscriptionController::class, 'index'])->name('payment-subscription.index');
    Route::get('/payment-subscription/create', [PaymentSubscriptionController::class, 'create'])->name('payment-subscription.create');
    Route::post('/payment-subscription', [PaymentSubscriptionController::class, 'store'])->name('payment-subscription.store');
    Route::get('/payment-subscription/{id}/edit', [PaymentSubscriptionController::class, 'edit'])->name('payment-subscription.edit');
    Route::put('/payment-subscription/{id}', [PaymentSubscriptionController::class, 'update'])->name('payment-subscription.update');
    Route::delete('/payment-subscription/{id}', [PaymentSubscriptionController::class, 'destroy'])->name('payment-subscription.destroy');

    // Billing & Plans
    Route::get('/billing', [App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/update', [App\Http\Controllers\BillingController::class, 'update'])->name('billing.update');

    Route::get('/performance-insights/list', [App\Http\Controllers\UserDailyPerformanceInsightController::class, 'index'])->name('performance-insights.index');
});

// Testing
Route::get('/emails/fetch', [EmailController::class, 'fetchEmails']);

// Today: open calendar in list view
Route::get('/today', function ()
{
    return redirect()->to(route('app-calendar').'?view=listMonth');
})->name('today')->middleware('auth');

// Calendar (local DB)
Route::middleware(['auth'])->prefix('app')->group(function ()
{
    Route::get('/calendar', [Calendar::class, 'index'])->name('app-calendar');
    Route::get('/calendar/events', [Calendar::class, 'events'])->name('app-calendar-events');
    Route::post('/calendar/events', [Calendar::class, 'store'])->name('app-calendar-events-store');
    Route::put('/calendar/events/{event}', [Calendar::class, 'update'])->name('app-calendar-events-update');
    Route::delete('/calendar/events/{event}', [Calendar::class, 'destroy'])->name('app-calendar-events-destroy');
});

Route::get('/app/invoice/list', [InvoiceList::class, 'index'])->name('app-invoice-list');
Route::get('/pages/account-settings-account', [AccountSettingsAccount::class, 'index'])->name('pages-account-settings-account');

// CMS — legal documents under /legal (legacy URLs redirect with 301)
Route::redirect('/terms', '/legal/terms', 301);
Route::redirect('/privacy', '/legal/privacy', 301);
Route::redirect('/security', '/legal/security', 301);
Route::redirect('/sla', '/legal/sla', 301);
Route::redirect('/cookies', '/legal/cookies', 301);

Route::get('/legal', [LegalDocumentsController::class, 'index'])->name('legal.index');
Route::get('/legal/terms', [LegalDocumentsController::class, 'terms'])->name('terms');
Route::get('/legal/privacy', [LegalDocumentsController::class, 'privacy'])->name('privacy');
Route::get('/legal/cookies', [LegalDocumentsController::class, 'cookies'])->name('cookies');
Route::get('/legal/security', [LegalDocumentsController::class, 'security'])->name('security');
Route::get('/legal/sla', [LegalDocumentsController::class, 'sla'])->name('sla');
Route::get('/legal/application', [LegalDocumentsController::class, 'application'])->name('legal.application');
Route::get('/legal/license', [LegalDocumentsController::class, 'license'])->name('legal.license');
Route::get('/legal/google-connection', [LegalDocumentsController::class, 'googleConnection'])->name('legal.google-connection');
Route::get('/legal/google-user-data', [LegalDocumentsController::class, 'googleUserData'])->name('legal.google-user-data');
Route::get('/legal/data-deletion', [LegalDocumentsController::class, 'dataDeletion'])->name('legal.data-deletion');

Route::get('/unsubscribe/{email}', [MessageController::class, 'unsubscribe']);

// Notification tracking routes (no auth required)
Route::get('/track/{token}', [NotificationTrackingController::class, 'track'])->name('notification.track');
Route::get('/track/{token}/click', [NotificationTrackingController::class, 'trackClick'])->name('notification.track.click');
Route::get('/notification/{notification}/stats', [NotificationTrackingController::class, 'getStats'])->name('notification.stats')->middleware('auth');

Route::view('/strategy', 'strategy.index')->name('strategy.index')->middleware('auth');
Route::get('/organization', [EnterpriseOrganizationController::class, 'index'])->name('organization.index');
Route::resource('organization', EnterpriseOrganizationController::class)->except(['index', 'show']);

Route::get('/department/list', [EnterpriseDepartmentController::class, 'index'])->name('department.index');
Route::get('/department/create', [EnterpriseDepartmentController::class, 'create'])->name('department.create');
Route::post('/department', [EnterpriseDepartmentController::class, 'store'])->name('department.store');
Route::get('/department/{department}/edit', [EnterpriseDepartmentController::class, 'edit'])->name('department.edit');
Route::put('/department/{department}', [EnterpriseDepartmentController::class, 'update'])->name('department.update');
Route::delete('/department/{department}', [EnterpriseDepartmentController::class, 'destroy'])->name('department.destroy');

Route::get('/notes', function ()
{
    return view('notes.index');
})->name('notes.index');

// Kanban
Route::get('/kanban', [KanbanController::class, 'index'])->name('kanban');

Route::get('/lead', [LeadController::class, 'create'])->name('lead.create');
Route::post('/lead', [LeadController::class, 'store'])->name('lead.store');

// Editor (team landing uses GrapesJS; HTML stored in team_settings)
Route::get('pages/landing/editor', [PageController::class, 'teamLandingEditor'])
    ->middleware('auth')
    ->name('page.team-landing-editor');
Route::post('pages/landing/editor', [PageController::class, 'teamLandingEditorStore'])
    ->middleware('auth')
    ->name('page.team-landing-editor.store');
Route::get('pages/{page}/editor', [PageController::class, 'editor'])->name('page.edit');
Route::get('pages/{page}', [PageController::class, 'show'])->name('page.view');

// WhatsApp webhook routes (canonical - without hash)
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handleIncomingMessage'])
    ->name('whatsapp.webhook');
Route::post('/whatsapp/status', [WhatsAppWebhookController::class, 'handleMessageStatus'])
    ->name('whatsapp.status');
Route::post('/whatsapp/fallback', [WhatsAppWebhookController::class, 'handleFallback'])
    ->name('whatsapp.fallback');

// WhatsApp webhook routes (canonical - team-specific with hash)
Route::post('/whatsapp/webhook/{hash}', [WhatsAppWebhookController::class, 'handleIncomingMessage'])
    ->name('whatsapp.webhook.team');
Route::post('/whatsapp/status/{hash}', [WhatsAppWebhookController::class, 'handleMessageStatus'])
    ->name('whatsapp.status.team');
Route::post('/whatsapp/fallback/{hash}', [WhatsAppWebhookController::class, 'handleFallback'])
    ->name('whatsapp.fallback.team');

// Twilio webhook routes (legacy aliases)
Route::post('/twilio/webhook', [TwilioWebhookController::class, 'handleIncomingMessage'])
    ->name('twilio.webhook');
Route::post('/twilio/status', [TwilioWebhookController::class, 'handleMessageStatus'])
    ->name('twilio.status');
Route::post('/twilio/fallback', [TwilioWebhookController::class, 'handleFallback'])
    ->name('twilio.fallback');
Route::post('/twilio/webhook/{hash}', [TwilioWebhookController::class, 'handleIncomingMessage'])
    ->name('twilio.webhook.team');
Route::post('/twilio/status/{hash}', [TwilioWebhookController::class, 'handleMessageStatus'])
    ->name('twilio.status.team');
Route::post('/twilio/fallback/{hash}', [TwilioWebhookController::class, 'handleFallback'])
    ->name('twilio.fallback.team');

// Debug route for testing JSON response (no auth required)
Route::get('/debug-units', function ()
{
    $fare = \App\Models\Fare::with('units')->find(1);
    if (! $fare)
    {
        return response()->json(['error' => 'Fare not found']);
    }

    $units = $fare->units->map(function ($unit)
    {
        return [
            'id' => $unit->id,
            'type' => $unit->type,
            'label' => $unit->type,
        ];
    });

    return response()->json([
        'units' => $units,
        'success' => true,
        'fare_team_id' => $fare->team_id,
        'fare_name' => $fare->name,
    ]);
})->name('debug-units');

// Debug route for user authentication info
Route::get('/debug-user', function ()
{
    if (! auth()->check())
    {
        return response()->json(['error' => 'Not authenticated']);
    }

    $user = auth()->user();
    $team = $user->currentTeam;

    return response()->json([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'team_id' => $team ? $team->id : null,
        'team_name' => $team ? $team->name : null,
        'authenticated' => true,
    ]);
})->middleware('auth')->name('debug-user');

/*
 * OVH API Routes
 */
Route::prefix('ovh')->group(function ()
{
    Route::get('/dashboard', [OvhApiController::class, 'dashboard'])->name('ovh.dashboard');
    Route::get('/invoices', [OvhApiController::class, 'getInvoices'])->name('ovh.invoices');
    Route::get('/services', [OvhApiController::class, 'getServices'])->name('ovh.services');
    Route::get('/sync-domains', [OvhApiController::class, 'syncDomains'])->name('ovh.sync-domains');
});

// Claude Prompts
Route::prefix('claude')->name('claude.')->middleware(['auth'])->group(function ()
{
    Route::get('/prompts', [App\Http\Controllers\ClaudePromptController::class, 'index'])->name('prompts.index');
    Route::get('/prompts/create', [App\Http\Controllers\ClaudePromptController::class, 'create'])->name('prompts.create');
    Route::post('/prompts', [App\Http\Controllers\ClaudePromptController::class, 'store'])->name('prompts.store');
    Route::get('/prompts/{prompt}/edit', [App\Http\Controllers\ClaudePromptController::class, 'edit'])->name('prompts.edit');
    Route::put('/prompts/{prompt}', [App\Http\Controllers\ClaudePromptController::class, 'update'])->name('prompts.update');
    Route::delete('/prompts/{prompt}', [App\Http\Controllers\ClaudePromptController::class, 'destroy'])->name('prompts.destroy');
    Route::post('/prompts/activate', [App\Http\Controllers\ClaudePromptController::class, 'activate'])->name('prompts.activate');
    Route::post('/prompts/preview', [App\Http\Controllers\ClaudePromptController::class, 'preview'])->name('prompts.preview');
});

// Language Variants
Route::middleware(['auth'])->prefix('language/variants')->name('language-variants.')->group(function ()
{
    Route::get('/', [App\Http\Controllers\LanguageVariantController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\LanguageVariantController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\LanguageVariantController::class, 'store'])->name('store');
    Route::get('/{languageVariant}/edit', [App\Http\Controllers\LanguageVariantController::class, 'edit'])->name('edit');
    Route::put('/{languageVariant}', [App\Http\Controllers\LanguageVariantController::class, 'update'])->name('update');
    Route::delete('/{languageVariant}', [App\Http\Controllers\LanguageVariantController::class, 'destroy'])->name('destroy');
    Route::get('/by-language/{baseLanguage}', [App\Http\Controllers\LanguageVariantController::class, 'getVariants'])->name('get-variants');
});

/*
 * CMS7 Routes - Legacy database
 */
Route::get('/cms7/empresa/{id}', [App\Http\Controllers\Cms7Controller::class, 'enterpriseDetails'])
    ->name('cms7.empresa')
    ->middleware(['auth', 'verified']);

// User linking routes - unified page
Route::get('/user-link/{type}/{id}', [ContactController::class, 'showUserLinkPage'])->name('user-link.show');
Route::post('/user-link/{type}/{id}/link', [ContactController::class, 'processUserLink'])->name('user-link.process');
Route::post('/user-link/{type}/{id}/create', [ContactController::class, 'processUserCreate'])->name('user-link.create');
Route::get('/user-unlink/{type}/{id}', [ContactController::class, 'showUserUnlinkPage'])->name('user-unlink.show');
Route::post('/user-unlink/{type}/{id}/confirm', [ContactController::class, 'processUserUnlink'])->name('user-unlink.process');

Route::post('/collaborator/{id}/documents', [CollaboratorController::class, 'uploadDocument'])->name('collaborator.documents.upload');
Route::delete('/collaborator/{id}/documents/{media}', [CollaboratorController::class, 'destroyDocument'])->name('collaborator.documents.destroy');

// Debug route for availability filtering
Route::get('/collaborator/debug/availability', [CollaboratorController::class, 'debugAvailability'])->name('collaborator.debug.availability');

// Profile Update Routes
Route::prefix('profile-update')->name('profile-update.')->middleware(['auth', 'verified'])->group(function ()
{
    Route::get('/', [App\Http\Controllers\Frontend\ProfileUpdateController::class, 'index'])->name('index');
    Route::post('/', [App\Http\Controllers\Frontend\ProfileUpdateController::class, 'store'])->name('store');
    Route::post('/get-rates', [App\Http\Controllers\Frontend\ProfileUpdateController::class, 'getRatesForLanguagePair'])->name('get-rates');
});

Route::get('message/track/{token}', [MessageTrackingController::class, 'track'])->name('message.track');
Route::get('message/track/click/{token}', [MessageTrackingController::class, 'trackClick'])->name('message.track.click');

// MailBaby webhooks (public route - no authentication required)
Route::post('webhooks/mailbaby', [App\Http\Controllers\MailBabyWebhookController::class, 'handle'])->name('mailbaby.webhook');

// WhatsApp Cart Testing Routes (available in all environments)
Route::prefix('test-cart')->group(function ()
{
    Route::get('/', [App\Http\Controllers\TestCartController::class, 'index'])->name('test.cart.index');
    Route::post('/process', [App\Http\Controllers\TestCartController::class, 'processMessage'])->name('test.cart.process');
    Route::get('/status', [App\Http\Controllers\TestCartController::class, 'cartStatus'])->name('test.cart.status');
    Route::post('/clear', [App\Http\Controllers\TestCartController::class, 'clearCart'])->name('test.cart.clear');
});

// Accounting routes (Billing module) - Stripe integration
Route::middleware(['web', 'auth'])->group(function ()
{
    Route::get('/accounting', [App\Http\Controllers\AccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting/invoice/{id}', [App\Http\Controllers\AccountingController::class, 'showInvoice'])->name('accounting.invoice');
    Route::get('/accounting/invoice/{id}/download', [App\Http\Controllers\AccountingController::class, 'downloadInvoice'])->name('accounting.invoice.download');
    Route::get('/accounting/customer/{id}', [App\Http\Controllers\AccountingController::class, 'customerInvoices'])->name('accounting.customer');
    Route::get('/accounting/download-quarter', [App\Http\Controllers\AccountingController::class, 'downloadQuarterInvoices'])->name('accounting.download-quarter');
    Route::get('/accounting/download-quarter-csv', [App\Http\Controllers\AccountingController::class, 'downloadQuarterCsv'])->name('accounting.download-quarter-csv');
});

// User Manual Routes (Public - No Authentication Required)
Route::prefix('manual')->name('manual.')->group(function ()
{
    Route::get('/', [ManualController::class, 'index'])->name('index');
    Route::get('/getting-started', [ManualController::class, 'gettingStarted'])->name('getting-started');
    Route::get('/dashboard', [ManualController::class, 'dashboard'])->name('dashboard');
    Route::get('/contacts', [ManualController::class, 'contacts'])->name('contacts');
    Route::get('/clients', [ManualController::class, 'clients'])->name('clients');
    Route::get('/collaborators', [ManualController::class, 'collaborators'])->name('collaborators');
    Route::get('/services', [ManualController::class, 'services'])->name('services');
    Route::get('/projects', [ManualController::class, 'projects'])->name('projects');
    Route::get('/tasks', [ManualController::class, 'tasks'])->name('tasks');
    Route::get('/chat', [ManualController::class, 'chat'])->name('chat');
    Route::get('/products-and-orders', [ManualController::class, 'productsAndOrders'])->name('products-and-orders');
    Route::get('/billing', [ManualController::class, 'billing'])->name('billing');
    Route::get('/campaigns', [ManualController::class, 'campaigns'])->name('campaigns');
    Route::get('/team', [ManualController::class, 'team'])->name('team');
    Route::get('/more-features', [ManualController::class, 'moreFeatures'])->name('more-features');
});

// Help Documentation Routes (Public - No Authentication Required)
Route::prefix('help')->name('help.')->group(function ()
{
    Route::get('/', [HelpController::class, 'index'])->name('index');
    Route::get('/usage', [HelpController::class, 'usage'])->name('usage');
    Route::get('/chat-assistant', [HelpController::class, 'chatAssistant'])->name('chat-assistant');
    Route::get('/contacts', [HelpController::class, 'contacts'])->name('contacts');
    Route::get('/api', [HelpController::class, 'api'])->name('api');
    Route::get('/api/authentication', [HelpController::class, 'apiAuthentication'])->name('api.authentication');
    Route::get('/api/contacts', [HelpController::class, 'apiContacts'])->name('api.contacts');
    Route::get('/api/contents', [HelpController::class, 'apiContents'])->name('api.contents');
    Route::get('/api/enterprises', [HelpController::class, 'apiEnterprises'])->name('api.enterprises');
    Route::get('/api/payments', [HelpController::class, 'apiPayments'])->name('api.payments');
    Route::get('/api/products', [HelpController::class, 'apiProducts'])->name('api.products');
    Route::get('/api/orders', [HelpController::class, 'apiOrders'])->name('api.orders');
    Route::get('/api/tasks', [HelpController::class, 'apiTasks'])->name('api.tasks');
    Route::get('/api/prompts', [HelpController::class, 'apiPrompts'])->name('api.prompts');

    Route::get('/environment-variables', [HelpController::class, 'environmentVariables'])->name('environment-variables');
    Route::get('/environment-variables/google-analytics', [HelpController::class, 'environmentVariablesGoogleAnalytics'])->name('environment-variables.google-analytics');
    Route::get('/environment-variables/google-people-calendar', [HelpController::class, 'googlePeopleCalendarSync'])->name('environment-variables.google-people-calendar');
    Route::get('/team-social-networks', [HelpController::class, 'teamSocialNetworks'])->name('team-social-networks');
    Route::get('/woocommerce-configuration', [HelpController::class, 'woocommerceConfiguration'])->name('woocommerce-configuration');
    Route::get('/postgresql-search-unaccent', [HelpController::class, 'postgresqlSearchUnaccent'])->name('postgresql-search-unaccent');
    Route::get('/email-spf-dns', [HelpController::class, 'emailSpfDns'])->name('email-spf-dns');
    Route::get('/stripe-webhook', [HelpController::class, 'stripeWebhook'])->name('stripe-webhook');
});

// Fallback route for 404 errors - must be at the end
Route::fallback(function ()
{
    return response()->view('errors.404', [], 404);
});
