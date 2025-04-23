<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\LegalDocumentsController;
use App\Http\Controllers\pages\AccountSettingsAccount;
use App\Http\Controllers\apps\Calendar;
use App\Http\Controllers\apps\InvoiceList;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\List60Controller;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EnterpriseOrganizationController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\TeamSettingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\HostingController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\OvhApiController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TemplateController;

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

// main
Route::get('/', [HomeController::class, 'index']);
Route::get('/home', [PageController::class, 'home'])->name('home');
Route::get('/dashboard/analytics', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');

// errors
Route::get('misc-error', function () {
    return view('content.pages.pages-misc-error');
})->name('404');

Route::get('/error-without-team', function () {
    return view('error-without-team');
})->name('error-without-team');

// account
Route::get('/app/calendar', [Calendar::class, 'index'])->name('app-calendar');
Route::get('/app/invoice/list', [InvoiceList::class, 'index'])->name('app-invoice-list');
Route::get('/pages/account-settings-account', [AccountSettingsAccount::class, 'index'])->name('pages-account-settings-account');

// CMS
Route::get('/terms', [LegalDocumentsController::class, 'terms'])->name('terms');
Route::get('/privacy', [LegalDocumentsController::class, 'privacy'])->name('privacy');
Route::get('/security', [LegalDocumentsController::class, 'security'])->name('security');
Route::get('/sla', [LegalDocumentsController::class, 'sla'])->name('sla');
Route::get('/legal/{document}', [LegalDocumentsController::class, 'show'])->name('legal.show');

Route::get('/unsubscribe/{email}', [MessageController::class, 'unsubscribe']);

Route::middleware(['auth'])->group(function ()
{
   Route::get('/dashboard', function ()
    {
        return redirect()->route('dashboard');
    });
    
    // Team Settings
    Route::get('/team/{team}/settings', [TeamSettingController::class, 'index'])->name('team-settings.index');
    Route::get('/team/{team}/settings/{group?}', [TeamSettingController::class, 'edit'])->name('team-settings.edit');
    Route::put('/team/{team}/settings', [TeamSettingController::class, 'update'])->name('team-settings.update');
    
    // Categories Management
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{id}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/order', [CategoryController::class, 'updateOrder'])->name('categories.order');
    Route::get('/categories/{id}/items', [CategoryController::class, 'showItems'])->name('categories.items');
    
    // User Management
    Route::get('/user-management', [UserManagement::class, 'UserManagement'])->name('user-management');
    Route::resource('/user-list', UserManagement::class);

    Route::get('/account-management', [AccountController::class, 'index'])->name('account-management');
    Route::get('/account-management/{id}/edit', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account-management/{id}', [AccountController::class, 'update'])->name('account.update');
    Route::post('/account-management', [AccountController::class, 'store'])->name('account.store');

    // Contacts
    Route::get('/contact/search', action: [contactController::class, 'search'])->name('contact.search');
    Route::get('/contact/list', [contactController::class, 'index'])->name('contact-list');
    Route::post('/contact/end-action/{id}', [contactController::class, 'endAction'])->name('contact.end-action');
    Route::post('/contact/upload-file', [contactController::class, 'UploadFile'])->name('contact.upload-file');
    Route::get('/contact/import', [ContactController::class, 'showImportForm'])->name('contact.import');
    
    Route::get('/contacts/import-mapping', action: [ContactController::class, 'importMapping'])->name('contact.import-mapping');
    Route::post('/contact/upload-file-mapping', [ContactController::class, 'uploadFileForMapping'])
    ->name('contact.upload-file-mapping');
    Route::post('/contact/process-mapping', [ContactController::class, 'processMapping'])
        ->name('contact.process-mapping');

    Route::get('/contact/create', [contactController::class, 'create'])->name('contact.create');
    Route::get('/contact/{id}', [contactController::class, 'show'])->name('contact.show');
    Route::get('/contact/{id}/edit', [contactController::class, 'edit'])->name('contact.edit');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::put('/contact/{id}', [ContactController::class, 'update'])->name('contact.update');
    Route::delete('/contact/{id}', [contactController::class, 'destroy'])->name('contact.destroy');
    
    Route::post('/contact/{id}/update-sentiment', [contactController::class, 'updateSentiment'])->name('contact.update-sentiment');
    Route::patch('/contact/{id}/notes', [ContactController::class, 'updateNotes'])->name('contact.update-notes');

    // Clients
    Route::get('/client/list', [ClientController::class, 'index'])
        ->middleware('role:admin,colaborator')
        ->name('client-list');
        
    Route::post('/client/end-action/{id}', [ClientController::class, 'endAction'])->name('client.end-action');
    Route::get('/client/import', [ClientController::class, 'showImportForm'])->name('client.import');
    Route::post('/client/import-excel', [ClientController::class, 'importExcel'])->name('client.import-excel');

    Route::get('/client/create', [ClientController::class, 'create'])->name('client.create');
    Route::get('/client/{id}', [ClientController::class, 'show'])->name('client.show');
    Route::get('/client/{id}/edit', [ClientController::class, 'edit'])->name('client.edit');
    Route::post('/client', [ClientController::class, 'store'])->name('client.store');
    Route::put('/client/{id}', [ClientController::class, 'update'])->name('client.update');
    Route::delete('/client/{id}', [ClientController::class, 'destroy'])->name('client.destroy');

    // List60
    Route::get('/list60/list', [List60Controller::class, 'index'])->name('list60-list');
    Route::post('/list60', [List60Controller::class, 'store'])->name('list60.store');
    Route::delete('/list60/{id}', [List60Controller::class, 'destroy'])->name('list60.destroy');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/messages/{phone}', [ChatController::class, 'getMessages'])->name('chat.messages');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/send-template', [ChatController::class, 'sendTemplateMessage'])->name('chat.send-template');

    // Mail
    Route::get('/mail/list', [MailController::class, 'index'])->name('mail-list');

    // Services
    Route::get('/service/list', [ServiceController::class, 'index'])->name('service-list')->middleware('role:admin');
    Route::get('/service/projection', [ServiceController::class, 'projectBilling'])->name('service.projectBilling')->middleware('role:admin');
    Route::get('/service/{id}', [ServiceController::class, 'show'])->name('service.show')->middleware('role:admin');
    Route::get('/service/{id}/edit', [ServiceController::class, 'edit'])->name('service.edit')->middleware('role:admin');
    Route::post('/service', [ServiceController::class, 'store'])->name('service.store')->middleware('role:admin');
    Route::put('/service/{id}', [ServiceController::class, 'update'])->name('service.update')->middleware('role:admin');
    Route::delete('/service/{id}', [ServiceController::class, 'destroy'])->name('service.destroy')->middleware('role:admin');

    // Projects
    Route::get('/project/list', [ProjectController::class, 'index'])->name('project-list');
    Route::get('/project/create', [ProjectController::class, 'create'])->name('project.create');
    Route::get('/project/{id}', [ProjectController::class, 'show'])->name('project.show');
    Route::get('/project/{id}/edit', [ProjectController::class, 'edit'])->name('project.edit');
    Route::post('/project', [ProjectController::class, 'store'])->name('project.store');
    Route::put('/project/{id}', [ProjectController::class, 'update'])->name('project.update');
    Route::delete('/project/{id}', [ProjectController::class, 'destroy'])->name('project.destroy');

    // Task Routes
    Route::get('/task/list', [TaskController::class, 'index'])->name('task.index');
    Route::get('/task/create', [TaskController::class, 'create'])->name('task.create');
    Route::get('/task/{id}', [TaskController::class, 'show'])->name('task.show');
    Route::get('/task/{id}/edit', [TaskController::class, 'edit'])->name('task.edit');
    Route::post('/task', [TaskController::class, 'store'])->name('task.store');
    Route::put('/task/{id}', [TaskController::class, 'update'])->name('task.update');
    Route::delete('/task/{id}', [TaskController::class, 'destroy'])->name('task.destroy');

    // Hosting
    Route::get('/hosting', [HostingController::class, 'index'])->name('hosting.index');
    Route::get('/hosting/data', [HostingController::class, 'data'])->name('hosting.data');
    
    // Domains
    Route::resource('domain', DomainController::class);
    Route::post('/domain/{domain}/refresh', [DomainController::class, 'refresh'])->name('domain.refresh');
    Route::post('/domain/{domain}/toggle-suspension', [DomainController::class, 'toggleSuspension'])->name('domain.toggle-suspension');
    
    // Accounting
    Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting/invoice/{id}', [AccountingController::class, 'showInvoice'])->name('accounting.invoice');
    Route::get('/accounting/invoice/{id}/download', [AccountingController::class, 'downloadInvoice'])->name('accounting.invoice.download');
    Route::get('/accounting/customer/{id}', [AccountingController::class, 'customerInvoices'])->name('accounting.customer');
    Route::get('/accounting/download-quarter', [AccountingController::class, 'downloadQuarterInvoices'])->name('accounting.download-quarter');
    Route::get('/accounting/download-quarter-csv', [AccountingController::class, 'downloadQuarterCsv'])->name('accounting.download-quarter-csv');

    // Messages
    Route::get('message/list', [MessageController::class, 'index'])->name('message-list');
    Route::get('message/create', [MessageController::class, 'create'])->name('message.create');
    Route::get('message/{id}', [MessageController::class, 'show'])->name('message.show');
    Route::get('message/{id}/edit', [MessageController::class, 'edit'])->name('message.edit');
    Route::post('message', [MessageController::class, 'store'])->name('message.store');
    Route::put('message/{id}', [MessageController::class, 'update'])->name('message.update');
    Route::delete('message/{id}', [MessageController::class, 'destroy'])->name('message.destroy');

    Route::get('/send-sms', [MessageController::class, 'sendSmsMessage']);
    Route::get('/send-whatsapp', [MessageController::class, 'sendWhatsAppMessage']);
    Route::get('/send-email', [MessageController::class, 'sendSendGridMessage']);

    // Templates
    Route::get('/template/list', [TemplateController::class, 'index'])->name('template-list');
    Route::get('/template/create', [TemplateController::class, 'create'])->name('template.create');
    Route::get('/template/{hashedId}', [TemplateController::class, 'show'])->name('template.show');
    Route::get('/template/{hashedId}/edit', [TemplateController::class, 'edit'])->name('template.edit');
    Route::post('/template', [TemplateController::class, 'store'])->name('template.store');
    Route::put('/template/{hashedId}', [TemplateController::class, 'update'])->name('template.update');
    Route::delete('/template/{hashedId}', [TemplateController::class, 'destroy'])->name('template.destroy');
    Route::get('/template/{hashedId}/editor', [TemplateController::class, 'editor'])->name('template.editor');
    Route::get('/template/view/{hashedId}', [TemplateController::class, 'show'])->name('template.view');
});

// Testing
Route::get('/emails/fetch', [EmailController::class, 'fetchEmails']);

Route::view('/strategy', 'strategy.index')->name('strategy.index');
Route::get('/organization', [EnterpriseOrganizationController::class, 'index'])->name('organization.index');
Route::resource('organization', EnterpriseOrganizationController::class)->except(['index', 'show']);

Route::get('/notes', function () {
    return view('notes.index');
})->name('notes.index');

// Kanban
Route::get('/kanban', [KanbanController::class, 'index'])->name('kanban');

Route::get('/lead', [LeadController::class, 'create'])->name('lead.create');
Route::post('/lead', [LeadController::class, 'store'])->name('lead.store');

// Editor
Route::get('pages/{page}/editor', [PageController::class, 'editor'])->name('page.edit');
Route::get('pages/{page}', [PageController::class, 'show'])->name('page.view');

// Twilio Webhook Routes
Route::post('/twilio/webhook', [TwilioWebhookController::class, 'handleIncomingMessage'])
    ->name('twilio.webhook');
Route::post('/twilio/status', [TwilioWebhookController::class, 'handleMessageStatus'])
    ->name('twilio.status');
Route::post('/twilio/fallback', [TwilioWebhookController::class, 'handleFallback'])
    ->name('twilio.fallback');

/*
 * OVH API Routes
 */
Route::prefix('ovh')->group(function () {
    Route::get('/dashboard', [OvhApiController::class, 'dashboard'])->name('ovh.dashboard');
    Route::get('/invoices', [OvhApiController::class, 'getInvoices'])->name('ovh.invoices');
    Route::get('/services', [OvhApiController::class, 'getServices'])->name('ovh.services');
    Route::get('/sync-domains', [OvhApiController::class, 'syncDomains'])->name('ovh.sync-domains');
});
