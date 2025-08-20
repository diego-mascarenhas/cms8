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
use App\Http\Controllers\Api\TeamContactController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamProjectController;
use App\Http\Controllers\Api\TemplateImportController;
use App\Http\Controllers\AuthController;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

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

// Mailgun Webhook (sin autenticación para recibir eventos)
Route::post('/mailgun/webhook', function (Request $request) {
    // Support both webhook formats: direct fields and event-data wrapper
    $eventData = $request->input('event-data');

    if ($eventData) {
        // New Mailgun format with event-data wrapper
        $event = $eventData['event'] ?? null;
        $recipient = $eventData['recipient'] ?? null;
        $messageId = $eventData['message']['headers']['message-id'] ?? null;
    } else {
        // Legacy/test format with direct fields
        $event = $request->input('event');
        $recipient = $request->input('recipient');
        $messageId = $request->input('message.headers.message-id')
                    ?: $request->input('Message-Id')
                    ?: $request->input('message_headers_message-id')
                    ?: $request->input('message-id');
    }

    Log::info('📧 Mailgun Webhook Received', [
        'timestamp' => now(),
        'event_type' => $event,
        'recipient' => $recipient,
        'domain' => $request->input('domain') ?: ($eventData['recipient-domain'] ?? null),
        'message_id' => $messageId,
        'webhook_format' => $eventData ? 'event-data' : 'legacy',
        'full_payload' => $request->all(),
    ]);

    // Find the corresponding MessageDelivery
    $delivery = null;

    // First try to find by provider_message_id
    if ($messageId) {
        $delivery = MessageDelivery::where('provider_message_id', $messageId)->first();
    }

    // If not found, try by recipient email
    if (! $delivery && $recipient) {
        $delivery = MessageDelivery::whereHas('contact', function ($q) use ($recipient) {
            $q->where('email', $recipient);
        })
            ->where('sent_at', '>=', now()->subDays(7)) // Only recent deliveries
            ->orderBy('sent_at', 'desc')
            ->first();
    }

    if ($delivery) {
        Log::info('📧 Found MessageDelivery for webhook', [
            'delivery_id' => $delivery->id,
            'message_id' => $delivery->message_id,
            'contact_email' => $delivery->contact->email,
        ]);
    } else {
        Log::warning('📧 No MessageDelivery found for webhook', [
            'event' => $event,
            'recipient' => $recipient,
            'message_id' => $messageId,
        ]);
    }

    // Process events and update delivery if found
    switch ($event) {
        case 'accepted':
            Log::info("📨 EMAIL ACCEPTED by Mailgun for {$recipient}");
            // No need to update delivery for accepted - just logging
            break;

        case 'delivered':
            Log::info("✅ EMAIL DELIVERED successfully to {$recipient}");
            if ($delivery) {
                $delivery->update([
                    'delivered_at' => now(),
                    'status_id' => 2, // 2 = delivered
                ]);
                Log::info('📊 Updated delivery status to delivered', ['delivery_id' => $delivery->id]);
            }
            break;

        case 'opened':
            Log::info("👁️ EMAIL OPENED by {$recipient}", [
                'user_agent' => $request->input('user-agent'),
                'client_info' => $request->input('client-info'),
            ]);
            if ($delivery && ! $delivery->opened_at) {
                $delivery->update([
                    'opened_at' => now(),
                    'status_id' => 3, // 3 = opened
                ]);
                Log::info('📊 Updated delivery status to opened', ['delivery_id' => $delivery->id]);
            }
            break;

        case 'clicked':
            $clickedUrl = $request->input('url') ?: ($eventData['url'] ?? null);

            Log::info("🖱️ EMAIL LINK CLICKED by {$recipient}", [
                'url' => $clickedUrl,
                'user_agent' => $request->input('user-agent'),
            ]);

            if ($delivery && ! $delivery->clicked_at) {
                $delivery->update([
                    'clicked_at' => now(),
                    // Keep current status_id, just add clicked_at timestamp
                ]);

                // ✅ Create Lead Conversion Link record
                if ($clickedUrl) {
                    MessageDeliveryLink::create([
                        'message_delivery_id' => $delivery->id,
                        'link' => $clickedUrl,
                    ]);

                    Log::info('🔗 Created Lead Conversion Link', [
                        'delivery_id' => $delivery->id,
                        'url' => $clickedUrl,
                    ]);
                }

                Log::info('📊 Updated delivery status to clicked', ['delivery_id' => $delivery->id]);
            }
            break;

        case 'unsubscribed':
            Log::info("🚫 UNSUBSCRIBED: {$recipient}", [
                'mailing_list' => $request->input('mailing-list'),
            ]);
            // Could update contact to unsubscribed status if needed
            break;

        case 'complained':
            Log::warning("📢 SPAM COMPLAINT from {$recipient}");
            if ($delivery) {
                $delivery->update([
                    'status_id' => 5, // 5 = complained
                    'provider_data' => array_merge($delivery->provider_data ?? [], [
                        'complained_at' => now()->toISOString(),
                        'complaint_reason' => 'spam',
                    ]),
                ]);
                Log::info('📊 Updated delivery status to complained', ['delivery_id' => $delivery->id]);
            }
            break;

        case 'permanent_fail':
            Log::error("❌ PERMANENT FAILURE to {$recipient}", [
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
                'code' => $request->input('code'),
            ]);
            if ($delivery) {
                $delivery->update([
                    'status_id' => 6, // 6 = permanent failure
                    'bounced_at' => now(),
                    'provider_data' => array_merge($delivery->provider_data ?? [], [
                        'failure_type' => 'permanent',
                        'failure_reason' => $request->input('reason'),
                        'failure_description' => $request->input('description'),
                        'failure_code' => $request->input('code'),
                    ]),
                ]);
                Log::info('📊 Updated delivery status to permanent failure', ['delivery_id' => $delivery->id]);
            }
            break;

        case 'temporary_fail':
            Log::warning("⏳ TEMPORARY FAILURE to {$recipient}", [
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
                'code' => $request->input('code'),
            ]);
            if ($delivery) {
                $delivery->update([
                    'provider_data' => array_merge($delivery->provider_data ?? [], [
                        'last_temporary_failure' => now()->toISOString(),
                        'failure_reason' => $request->input('reason'),
                        'failure_description' => $request->input('description'),
                        'failure_code' => $request->input('code'),
                    ]),
                ]);
                Log::info('📊 Updated delivery with temporary failure info', ['delivery_id' => $delivery->id]);
            }
            break;

            // Eventos legacy (por compatibilidad)
        case 'failed':
            Log::error("❌ EMAIL FAILED to {$recipient}", [
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
            ]);
            if ($delivery) {
                $delivery->update([
                    'status_id' => 6, // 6 = failed
                    'bounced_at' => now(),
                ]);
            }
            break;

        case 'bounced':
            Log::warning("⚠️ EMAIL BOUNCED from {$recipient}", [
                'error' => $request->input('error'),
            ]);
            if ($delivery) {
                $delivery->update([
                    'status_id' => 6, // 6 = bounced
                    'bounced_at' => now(),
                ]);
            }
            break;

        case 'dropped':
            Log::warning("🗑️ EMAIL DROPPED to {$recipient}", [
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
            ]);
            if ($delivery) {
                $delivery->update([
                    'status_id' => 6, // 6 = dropped
                    'provider_data' => array_merge($delivery->provider_data ?? [], [
                        'dropped_reason' => $request->input('reason'),
                        'dropped_description' => $request->input('description'),
                    ]),
                ]);
            }
            break;

        default:
            Log::info("📧 Unknown Mailgun event: {$event} for {$recipient}");
            break;
    }

    // Update aggregate statistics if delivery was found and updated
    if ($delivery && in_array($event, ['delivered', 'opened', 'clicked', 'permanent_fail', 'failed', 'bounced', 'dropped'])) {
        // Update MessageDeliveryStat
        $stats = MessageDeliveryStat::firstOrCreate(['message_id' => $delivery->message_id], [
            'subscribers' => 0,
            'sent' => 0,
            'delivered' => 0,
            'opened' => 0,
            'clicks' => 0,
            'failed' => 0,
        ]);

        // Recalculate stats from actual deliveries
        $deliveries = MessageDelivery::where('message_id', $delivery->message_id);

        $stats->update([
            'sent' => $deliveries->whereNotNull('sent_at')->count(),
            'delivered' => $deliveries->whereNotNull('delivered_at')->count(),
            'opened' => $deliveries->whereNotNull('opened_at')->count(),
            'clicks' => $deliveries->whereNotNull('clicked_at')->count(),
            'failed' => $deliveries->whereIn('status_id', [5, 6])->count(), // complained, failed, bounced
        ]);

        Log::info('📊 Updated message statistics', [
            'message_id' => $delivery->message_id,
            'sent' => $stats->sent,
            'delivered' => $stats->delivered,
            'opened' => $stats->opened,
            'clicks' => $stats->clicks,
            'failed' => $stats->failed,
        ]);
    }

    return response()->json(['status' => 'success']);
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
    Route::get('software/categories', [SoftwareController::class, 'categories'])->name('api.software.categories');
    Route::get('software/category/{category}', [SoftwareController::class, 'byCategory'])->name('api.software.byCategory');
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
