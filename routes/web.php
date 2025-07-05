<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\apps\Calendar;
use App\Http\Controllers\apps\InvoiceList;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EnterpriseOrganizationController;
use App\Http\Controllers\FareController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HostingController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LegalDocumentsController;
use App\Http\Controllers\List60Controller;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OvhApiController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\pages\AccountSettingsAccount;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\StylebookController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamSettingController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\UserFareController;
use Illuminate\Support\Facades\Route;

// auth
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// locale
Route::get('lang/{locale}', [LanguageController::class, 'swap']);

// main
Route::get('/', [HomeController::class, 'index']);
Route::get('/home', [PageController::class, 'home'])->name('home');
Route::get('/dashboard/analytics', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');
Route::get('/dashboard/collaborator', [CollaboratorController::class, 'dashboard'])->name('dashboard.collaborator')->middleware('auth');

// Adding routes for other dashboard types
Route::get('/dashboard/client', function () {
    // This view doesn't exist yet, so we'll redirect to collaborator for now
    return view('collaborator.dashboard');
})->name('dashboard.client')->middleware('auth');

Route::get('/dashboard/project', function () {
    // This view doesn't exist yet, so we'll redirect to collaborator for now
    return view('collaborator.dashboard');
})->name('dashboard.project')->middleware('auth');

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

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('dashboard');
    });

    // Team Settings
    Route::get('/team/{team}/settings', [TeamSettingController::class, 'index'])->name('team-settings.index');
    Route::get('/team/{team}/settings/{group?}', [TeamSettingController::class, 'edit'])->name('team-settings.edit');
    Route::put('/team/{team}/settings', [TeamSettingController::class, 'update'])->name('team-settings.update');

    // Team Valorations
    Route::get('/team/{team}/valorations', [TeamSettingController::class, 'valorations'])->name('team-settings.valorations');
    Route::post('/team/{team}/valorations', [TeamSettingController::class, 'storeValoration'])->name('team-settings.valorations.store');
    Route::put('/team/{team}/valorations/{valoration}', [TeamSettingController::class, 'updateValoration'])->name('team-settings.valorations.update');
    Route::delete('/team/{team}/valorations/{valoration}', [TeamSettingController::class, 'destroyValoration'])->name('team-settings.valorations.destroy');

    // Team API Tokens
    Route::get('/team/{team}/api-tokens', [TeamSettingController::class, 'apiTokens'])->name('team-settings.api-tokens');
    Route::post('/team/{team}/api-tokens/generate', [TeamSettingController::class, 'generateApiToken'])->name('team-settings.generate-api-token');
    Route::delete('/team/{team}/api-tokens/revoke', [TeamSettingController::class, 'revokeApiToken'])->name('team-settings.revoke-api-token');

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

    // Activity Log
    Route::get('/activity-log', [App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('/activity-log/statistics', [App\Http\Controllers\ActivityLogController::class, 'statistics'])->name('activity-log.statistics');
    Route::get('/activity-log/recent', [App\Http\Controllers\ActivityLogController::class, 'recent'])->name('activity-log.recent');
    Route::get('/activity-log/{activity}', [App\Http\Controllers\ActivityLogController::class, 'show'])->name('activity-log.show');
    Route::get('/activity-log/user/{userId}', [App\Http\Controllers\ActivityLogController::class, 'userActivities'])->name('activity-log.user');

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
    Route::post('/contact/upload-file-mapping', [ContactController::class, 'uploadFileForMapping'])->name('contact.upload-file-mapping');
    Route::post('/contact/process-mapping', [ContactController::class, 'processMapping'])->name('contact.process-mapping');
    Route::get('/contact/create', [contactController::class, 'create'])->name('contact.create');
    Route::get('/contact/{id}', [contactController::class, 'show'])->name('contact.show');
    Route::get('/contact/{id}/edit', [contactController::class, 'edit'])->name('contact.edit');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::put('/contact/{id}', [ContactController::class, 'update'])->name('contact.update');
    Route::delete('/contact/{id}', [contactController::class, 'destroy'])->name('contact.destroy');
    Route::post('/contact/{id}/update-sentiment', [contactController::class, 'updateSentiment'])->name('contact.update-sentiment');
    Route::patch('/contact/{id}/notes', [ContactController::class, 'updateNotes'])->name('contact.update-notes');
    Route::post('/contact/{id}/link-user', [ContactController::class, 'linkUser'])->name('contact.link-user');
    Route::post('/contact/{id}/unlink-user', [ContactController::class, 'unlinkUser'])->name('contact.unlink-user');
    Route::post('/contact/{id}/create-and-link-user', [ContactController::class, 'createAndLinkUser'])->name('contact.create-and-link-user');

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

    Route::get('/collaborator/{id}/notifications', [CollaboratorController::class, 'notifications'])->name('collaborator.notifications');
    Route::get('/collaborator/{id}/activity', [CollaboratorController::class, 'activity'])->name('collaborator.activity');
    Route::get('/collaborator/{id}/accept', [CollaboratorController::class, 'showAcceptForm'])->name('collaborator.accept');
    Route::post('/collaborator/{id}/accept', [CollaboratorController::class, 'processAccept'])->name('collaborator.process-accept');

    // Clients
    Route::get('/client/list', [ClientController::class, 'index'])
        ->middleware('role:admin,collaborator')
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
    Route::get('/service/create', [ServiceController::class, 'create'])->name('service.create')->middleware('role:admin');
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
    Route::get('/project/{id}/select-collaborators', [ProjectController::class, 'selectCollaborators'])->name('project.select-collaborators');
    Route::post('/project/{id}/filter-collaborators', [ProjectController::class, 'filterCollaborators'])->name('project.filter-collaborators');
    Route::post('/project/{id}/send-notifications', [ProjectController::class, 'sendCollaboratorNotifications'])->name('project.send-notifications');
    Route::delete('/project/{project}/remove-collaborator/{collaborator}', [ProjectController::class, 'removeCollaborator'])->name('project.remove-collaborator');
    Route::get('/project/service-template', [ProjectController::class, 'getServiceTemplate'])->name('project.get-service-template');
    Route::get('/project/fare-units', [ProjectController::class, 'getFareUnits'])->name('project.get-fare-units');
    Route::get('/project/{project}/add-services', [ProjectController::class, 'addServices'])->name('project.add-services');
    Route::post('/project/{project}/store-services', [ProjectController::class, 'storeServices'])->name('project.store-services');
Route::any('/project/{project}/debug-services', [ProjectController::class, 'debugServices'])->name('project.debug-services');
Route::get('/debug/fare-units', [ProjectController::class, 'debugFareUnits'])->name('debug.fare-units');
Route::get('/debug/test-fare/{fareId}', [ProjectController::class, 'testFareUnits'])->name('debug.test-fare');
Route::get('/debug/test-ajax', [ProjectController::class, 'testAjax'])->name('debug.test-ajax');

    // Task Routes
    Route::get('/task/list', [TaskController::class, 'index'])->name('task.index');
    Route::get('/task/create', [TaskController::class, 'create'])->name('task.create');
    Route::get('/task/{id}', [TaskController::class, 'show'])->name('task.show');
    Route::get('/task/{id}/edit', [TaskController::class, 'edit'])->name('task.edit');
    Route::post('/task', [TaskController::class, 'store'])->name('task.store');
    Route::put('/task/{id}', [TaskController::class, 'update'])->name('task.update');
    Route::delete('/task/{id}', [TaskController::class, 'destroy'])->name('task.destroy');

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

    // Fare Types
    Route::get('/fare', [FareController::class, 'index'])->name('fare.index');
    Route::get('/fare/create', [FareController::class, 'create'])->name('fare.create');
    Route::post('/fare', [FareController::class, 'store'])->name('fare.store');
    Route::get('/fare/{fare}', [FareController::class, 'show'])->name('fare.show');
    Route::get('/fare/{fare}/edit', [FareController::class, 'edit'])->name('fare.edit');
    Route::put('/fare/{fare}', [FareController::class, 'update'])->name('fare.update');
    Route::delete('/fare/{fare}', [FareController::class, 'destroy'])->name('fare.destroy');

    // Software Management
    Route::get('/software', [SoftwareController::class, 'index'])->name('software.index')->middleware('auth');
    Route::get('/software/create', [SoftwareController::class, 'create'])->name('software.create')->middleware('auth');
    Route::post('/software', [SoftwareController::class, 'store'])->name('software.store')->middleware('auth');
    Route::get('/software/{software}/edit', [SoftwareController::class, 'edit'])->name('software.edit')->middleware('auth');
    Route::put('/software/{software}', [SoftwareController::class, 'update'])->name('software.update')->middleware('auth');
    Route::delete('/software/{software}', [SoftwareController::class, 'destroy'])->name('software.destroy')->middleware('auth');
    Route::get('/software/autocomplete', [SoftwareController::class, 'autocomplete'])->name('software.autocomplete')->middleware('auth');

    // Certification Management
    Route::get('/certification', [CertificationController::class, 'index'])->name('certification.index')->middleware('auth');
    Route::get('/certification/create', [CertificationController::class, 'create'])->name('certification.create')->middleware('auth');
    Route::post('/certification', [CertificationController::class, 'store'])->name('certification.store')->middleware('auth');
    Route::get('/certification/{certification}/edit', [CertificationController::class, 'edit'])->name('certification.edit')->middleware('auth');
    Route::put('/certification/{certification}', [CertificationController::class, 'update'])->name('certification.update')->middleware('auth');
    Route::delete('/certification/{certification}', [CertificationController::class, 'destroy'])->name('certification.destroy')->middleware('auth');

    // Style Book Management
    Route::get('/stylebook', [StylebookController::class, 'index'])->name('stylebook.index')->middleware('auth');
    Route::get('/stylebook/create', [StylebookController::class, 'create'])->name('stylebook.create')->middleware('auth');
    Route::post('/stylebook', [StylebookController::class, 'store'])->name('stylebook.store')->middleware('auth');
    Route::get('/stylebook/{stylebook}', [StylebookController::class, 'show'])->name('stylebook.show')->middleware('auth');
    Route::get('/stylebook/{stylebook}/edit', [StylebookController::class, 'edit'])->name('stylebook.edit')->middleware('auth');
    Route::put('/stylebook/{stylebook}', [StylebookController::class, 'update'])->name('stylebook.update')->middleware('auth');
    Route::delete('/stylebook/{stylebook}', [StylebookController::class, 'destroy'])->name('stylebook.destroy')->middleware('auth');

    // Notification Management
    Route::get('/notification/list', [NotificationController::class, 'index'])->name('notification-list')->middleware('auth');
    Route::get('/notification/create', [NotificationController::class, 'create'])->name('notification.create')->middleware('auth');
    Route::post('/notification', [NotificationController::class, 'store'])->name('notification.store')->middleware('auth');
    Route::get('/notification/{notification}', [NotificationController::class, 'show'])->name('notification.show')->middleware('auth');
    Route::get('/notification/{notification}/edit', [NotificationController::class, 'edit'])->name('notification.edit')->middleware('auth');
    Route::put('/notification/{notification}', [NotificationController::class, 'update'])->name('notification.update')->middleware('auth');
    Route::delete('/notification/{notification}', [NotificationController::class, 'destroy'])->name('notification.destroy')->middleware('auth');
    Route::post('/notification/{notification}/send', [NotificationController::class, 'send'])->name('notification.send')->middleware('auth');
    Route::post('/notification/{notification}/resend', [NotificationController::class, 'resend'])->name('notification.resend')->middleware('auth');
    Route::post('/notification/get-template', [NotificationController::class, 'getTemplate'])->name('notification.get-template')->middleware('auth');
    Route::post('/notification/bulk-send', [NotificationController::class, 'bulkSend'])->name('notification.bulk-send')->middleware('auth');
    Route::post('/notification/bulk-delete', [NotificationController::class, 'bulkDelete'])->name('notification.bulk-delete')->middleware('auth');

    // Tarifas Personalizadas de Usuario
    Route::get('/user-fare', [UserFareController::class, 'index'])->name('user-fare.index');
    Route::get('/user-fare/create', [UserFareController::class, 'create'])->name('user-fare.create');
    Route::post('/user-fare', [UserFareController::class, 'store'])->name('user-fare.store');
    Route::get('/user-fare/{userFare}', [UserFareController::class, 'show'])->name('user-fare.show');
    Route::get('/user-fare/{userFare}/edit', [UserFareController::class, 'edit'])->name('user-fare.edit');
    Route::put('/user-fare/{userFare}', [UserFareController::class, 'update'])->name('user-fare.update');
    Route::delete('/user-fare/{userFare}', [UserFareController::class, 'destroy'])->name('user-fare.destroy');
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

// Claude Prompts
Route::prefix('claude')->name('claude.')->middleware(['auth'])->group(function () {
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
Route::middleware(['auth'])->prefix('language/variants')->name('language-variants.')->group(function () {
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
