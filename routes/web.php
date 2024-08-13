<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalDocumentsController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;


// Main Page Route
Route::get('/', [HomeController::class, 'index']);
Route::get('/home', [PageController::class, 'home'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');
Route::get('/dashboard/analytics', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');

// locale
Route::get('lang/{locale}', [LanguageController::class, 'swap']);

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

// CMS
Route::get('/terms', [LegalDocumentsController::class, 'terms'])->name('terms');
Route::get('/privacy', [LegalDocumentsController::class, 'privacy'])->name('privacy');
Route::get('/security', [LegalDocumentsController::class, 'security'])->name('security');
Route::get('/sla', [LegalDocumentsController::class, 'sla'])->name('sla');
Route::get('/legal/{document}', [LegalDocumentsController::class, 'show'])->name('legal.show');

Route::get('/unsubscribe/{email}', [MessageController::class, 'unsubscribe']);
Route::get('/services/project-billing', [ServiceController::class, 'projectBilling'])->name('service.projectBilling');

Route::middleware(['auth'])->group(function ()
{
   Route::get('/dashboard', function ()
    {
        return redirect()->route('dashboard');
    })->name('dashboard');

    // User Management
    Route::get('/user-management', [UserManagement::class, 'UserManagement'])->name('user-management');
    Route::resource('/user-list', UserManagement::class);

    // Clients
    Route::get('/app/client/list', [ClientController::class, 'index'])
        ->middleware('role:admin,colaborator')
        ->name('app-client-list');

    Route::get('/app/client/create', [ClientController::class, 'create'])->name('client.create');
    Route::get('/app/client/{id}', [ClientController::class, 'show'])->name('client.show');
    Route::get('/app/client/{id}/edit', [ClientController::class, 'edit'])->name('client.edit');
    Route::post('/app/client', [ClientController::class, 'store'])->name('client.store');
    Route::put('/app/client/{id}', [ClientController::class, 'update'])->name('client.update');
    Route::delete('/app/client/{id}', [ClientController::class, 'destroy'])->name('client.destroy');

    // Invoices
    Route::get('/app/invoice/list', [InvoiceController::class, 'index'])->name('app-invoice-list');
    Route::delete('/app/invoice/{id}', [InvoiceController::class, 'destroy'])->name('invoice.destroy');

    // Payments
    Route::get('/app/payment/list', [PaymentController::class, 'index'])->name('app-payment-list');
    Route::get('/app/payment/{id}/edit', [PaymentController::class, 'edit'])->name('payment.edit');
    Route::delete('/app/payment/{id}', [PaymentController::class, 'destroy'])->name('payment.destroy');

    // Communications
    Route::get('/app/communication/list', [CommunicationController::class, 'index'])->name('app-communication-list');
    Route::delete('/app/communication/{id}', [CommunicationController::class, 'destroy'])->name('communication.destroy');

    // WhatsApp
    Route::get('/app/whatsapp', [WhatsAppController::class, 'index'])->name('app-whatsapp');
});

// Editor
Route::get('pages/{page}/editor', [PageController::class, 'editor'])->name('page.edit');
Route::get('pages/{page}', [PageController::class, 'show'])->name('page.view');