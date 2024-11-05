<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalDocumentsController;
use App\Http\Controllers\DashboardController;
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
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');
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
    })->name('dashboard');

    // User Management
    Route::get('/user-management', [UserManagement::class, 'UserManagement'])->name('user-management');
    Route::resource('/user-list', UserManagement::class);

    // Contacts
    Route::get('/contact/search', action: [contactController::class, 'search'])->name('contact.search');
    Route::get('/contact/list', [contactController::class, 'index'])->name('contact-list');
    Route::post('/contact/end-action/{id}', [contactController::class, 'endAction'])->name('contact.end-action');
    Route::post('/contact/upload-file', [contactController::class, 'UploadFile'])->name('contact.upload-file');
    Route::get('/contact/import', [ContactController::class, 'showImportForm'])->name('contact.import');

    Route::get('/contact/create', [contactController::class, 'create'])->name('contact.create');
    Route::get('/contact/{id}', [contactController::class, 'show'])->name('contact.show');
    Route::get('/contact/{id}/edit', [contactController::class, 'edit'])->name('contact.edit');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::put('/contact/{id}', [ContactController::class, 'update'])->name('contact.update');
    Route::delete('/contact/{id}', [contactController::class, 'destroy'])->name('contact.destroy');
    
    Route::post('/contact/{id}/update-sentiment', [contactController::class, 'updateSentiment'])->name('contact.update-sentiment');

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
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/chat/list', [ChatController::class, 'index'])->name('chat-list');

});

// Testing
Route::get('/emails/fetch', [EmailController::class, 'fetchEmails']);

Route::view('/strategy', 'strategy.index')->name('strategy.index');
Route::get('/organization', [EnterpriseOrganizationController::class, 'index']);

Route::get('/notes', function () {
    return view('notes.index');
})->name('notes.index');
