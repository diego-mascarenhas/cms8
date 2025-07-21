<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificationController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LanguageVariantController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SoftwareController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamContactController;
use App\Http\Controllers\Api\TeamProjectController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TemplateImportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Category
    Route::get('category', [CategoryController::class, 'index']);

    // Message
    Route::get('message', [MessageController::class, 'index']);
    Route::get('message/{id}', [MessageController::class, 'show']);

    // Contacts - for user-based authentication (Sanctum tokens)
    Route::get('contacts', [ContactController::class, 'index']);
    Route::get('contacts/{id}', [ContactController::class, 'show']);

    // Projects - for user-based authentication (Sanctum tokens)
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{id}', [ProjectController::class, 'show']);

    // Services - for user-based authentication (Sanctum tokens)
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{id}', [ServiceController::class, 'show']);
    Route::post('services', [ServiceController::class, 'store']);
    Route::put('services/{id}', [ServiceController::class, 'update']);
    Route::delete('services/{id}', [ServiceController::class, 'destroy']);

    // Invoices - for user-based authentication (Sanctum tokens)
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::put('invoices/{id}', [InvoiceController::class, 'update']);
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy']);

    // Language Variants - for user-based authentication (Sanctum tokens)
    Route::get('language-variants', [LanguageVariantController::class, 'index']);
    Route::get('language-variants/{languageVariant}', [LanguageVariantController::class, 'show']);
    Route::post('language-variants', [LanguageVariantController::class, 'store']);
    Route::put('language-variants/{languageVariant}', [LanguageVariantController::class, 'update']);
    Route::delete('language-variants/{languageVariant}', [LanguageVariantController::class, 'destroy']);
    Route::get('language-variants/base/{baseLanguage}', [LanguageVariantController::class, 'getVariantsFor']);

    // Software - for user-based authentication (Sanctum tokens)
    // Define specific routes BEFORE resource routes to avoid conflicts
    Route::get('software/types', [SoftwareController::class, 'types'])->name('api.software.types');
    Route::get('software/categories', [SoftwareController::class, 'softwareTypes'])->name('api.software.categories');
    Route::get('software/type/{type}', [SoftwareController::class, 'byType'])->name('api.software.byType');
    Route::apiResource('software', SoftwareController::class)->names([
        'index' => 'api.software.index',
        'store' => 'api.software.store',
        'show' => 'api.software.show',
        'update' => 'api.software.update',
        'destroy' => 'api.software.destroy',
    ]);

    // Certifications - for user-based authentication (Sanctum tokens)
    // Define specific routes BEFORE resource routes to avoid conflicts
    Route::get('certifications/types', [CertificationController::class, 'types'])->name('api.certifications.types');
    Route::get('certifications/languages', [CertificationController::class, 'languages'])->name('api.certifications.languages');
    Route::get('certifications/language/{language}', [CertificationController::class, 'byLanguage'])->name('api.certifications.byLanguage');
    Route::apiResource('certifications', CertificationController::class)->names([
        'index' => 'api.certifications.index',
        'store' => 'api.certifications.store',
        'show' => 'api.certifications.show',
        'update' => 'api.certifications.update',
        'destroy' => 'api.certifications.destroy',
    ]);

    // Fares - for user-based authentication (Sanctum tokens)
    // Define specific routes BEFORE resource routes to avoid conflicts
    Route::get('fares/types', [FareController::class, 'types'])->name('api.fares.types');
    Route::get('fares/type/{type}', [FareController::class, 'byType'])->name('api.fares.byType');
    Route::apiResource('fares', FareController::class)->names([
        'index' => 'api.fares.index',
        'store' => 'api.fares.store',
        'show' => 'api.fares.show',
        'update' => 'api.fares.update',
        'destroy' => 'api.fares.destroy',
    ]);
});

Route::post('/register-application', [LicenseController::class, 'register']);

Route::get('/roles-permissions', [RolePermissionController::class, 'index']);

// Team API routes protected by team token
Route::middleware('team.token')->prefix('team')->group(function () {
    // Team information
    Route::get('/', [TeamController::class, 'index']);
    Route::get('/settings', [TeamController::class, 'settings']);

    // Team contacts
    Route::resource('contacts', TeamContactController::class);

    // Team projects
    Route::resource('projects', TeamProjectController::class);
});

Route::get('/fetch-html', [TemplateImportController::class, 'fetchHtml']);

// Public statistics route (no authentication required)
Route::get('/collaborator/service-statistics', [App\Http\Controllers\Api\CollaboratorController::class, 'getServiceStatistics']);
