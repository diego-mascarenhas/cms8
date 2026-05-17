<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificationController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LandingEmbedDemoController;
use App\Http\Controllers\Api\LanguageVariantController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\MailInboxController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationInboxController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SoftwareController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamAssistantController;
use App\Http\Controllers\Api\TeamContactController;
use App\Http\Controllers\Api\TeamContentController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamEnterpriseController;
use App\Http\Controllers\Api\TeamOrderController;
use App\Http\Controllers\Api\TeamPaymentController;
use App\Http\Controllers\Api\TeamProductController;
use App\Http\Controllers\Api\TeamProjectController;
use App\Http\Controllers\Api\TeamPromptController;
use App\Http\Controllers\Api\TemplateImportController;
use App\Http\Controllers\Api\TimeController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\UserAssistantController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProspectSearchController;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register API routes for your application. These
 * | routes are loaded by the RouteServiceProvider and all of them will
 * | be assigned to the "api" middleware group. Make something great!
 * |
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request)
{
    return $request->user();
});

Route::prefix('embed/demo')->middleware('throttle:60,1')->group(function ()
{
    Route::get('calendar', [LandingEmbedDemoController::class, 'calendar']);
    Route::post('assistant', [LandingEmbedDemoController::class, 'assistant']);
});

// Prospect Search (public, for React frontend / prospection)
Route::middleware('throttle:30,1')->group(function ()
{
    Route::get('/prospect-search/checkout-config', [ProspectSearchController::class, 'checkoutConfig'])->name('api.prospect-search.checkout-config');
    Route::post('/prospect-search/search', [ProspectSearchController::class, 'searchPeople'])->name('api.prospect-search.search');
    Route::post('/prospect-search/lead', [ProspectSearchController::class, 'storeLead'])->name('api.prospect-search.lead');
    Route::get('/prospect-search/access', [ProspectSearchController::class, 'access'])->name('api.prospect-search.access');
    Route::post('/prospect-search/checkout', [ProspectSearchController::class, 'createExportCheckout'])->name('api.prospect-search.checkout');
    Route::get('/prospect-search/export-csv', [ProspectSearchController::class, 'downloadExportCsv'])->name('api.prospect-search.export-csv');
    Route::get('/prospect-search/export-data', [ProspectSearchController::class, 'getExportData'])->name('api.prospect-search.export-data');
    Route::get('/prospect-search/trigger-save-export', [ProspectSearchController::class, 'triggerSaveExport'])->name('api.prospect-search.trigger-save-export');
    Route::get('/prospect-search/export-by-code', [ProspectSearchController::class, 'exportByCode'])->name('api.prospect-search.export-by-code');
});

// Mailgun Webhook (sin autenticación para recibir eventos)
Route::post('/mailgun/webhook', function (Request $request)
{
    // Support both webhook formats: direct fields and event-data wrapper
    $eventData = $request->input('event-data');

    if ($eventData)
    {
        // New Mailgun format with event-data wrapper
        $event = $eventData['event'] ?? null;
        $recipient = $eventData['recipient'] ?? null;
        $messageId = $eventData['message']['headers']['message-id'] ?? null;
    } else
    {
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
    if ($messageId)
    {
        $delivery = MessageDelivery::where('provider_message_id', $messageId)->first();
    }

    // If not found, try by recipient email
    if (! $delivery && $recipient)
    {
        $delivery = MessageDelivery::whereHas('contact', function ($q) use ($recipient)
        {
            $q->where('email', $recipient);
        })
            ->where('sent_at', '>=', now()->subDays(7))  // Only recent deliveries
            ->orderBy('sent_at', 'desc')
            ->first();
    }

    if ($delivery)
    {
        Log::info('📧 Found MessageDelivery for webhook', [
            'delivery_id' => $delivery->id,
            'message_id' => $delivery->message_id,
            'contact_email' => $delivery->contact->email,
        ]);
    } else
    {
        Log::warning('📧 No MessageDelivery found for webhook', [
            'event' => $event,
            'recipient' => $recipient,
            'message_id' => $messageId,
        ]);
    }

    // Process events and update delivery if found
    switch ($event)
    {
        case 'accepted':
            Log::info("📨 EMAIL ACCEPTED by Mailgun for {$recipient}");
            // No need to update delivery for accepted - just logging
            break;

        case 'delivered':
            Log::info("✅ EMAIL DELIVERED successfully to {$recipient}");
            if ($delivery)
            {
                $delivery->update([
                    'delivered_at' => now(),
                    'status_id' => 2,  // 2 = delivered
                ]);
                Log::info('📊 Updated delivery status to delivered', ['delivery_id' => $delivery->id]);
            }
            break;

        case 'opened':
            Log::info("👁️ EMAIL OPENED by {$recipient}", [
                'user_agent' => $request->input('user-agent'),
                'client_info' => $request->input('client-info'),
            ]);
            if ($delivery && ! $delivery->opened_at)
            {
                $delivery->update([
                    'opened_at' => now(),
                    'status_id' => 3,  // 3 = opened
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

            if ($delivery)
            {
                // Update clicked_at timestamp only if first click
                if (! $delivery->clicked_at)
                {
                    $delivery->update([
                        'clicked_at' => now(),
                        // Keep current status_id, just add clicked_at timestamp
                    ]);
                    Log::info('📊 Updated delivery status to clicked (first time)', ['delivery_id' => $delivery->id]);
                }

                // ✅ ALWAYS Create Lead Conversion Link record for each click
                if ($clickedUrl)
                {
                    MessageDeliveryLink::create([
                        'message_delivery_id' => $delivery->id,
                        'link' => $clickedUrl,
                    ]);

                    Log::info('🔗 Created Lead Conversion Link', [
                        'delivery_id' => $delivery->id,
                        'url' => $clickedUrl,
                        'click_number' => MessageDeliveryLink::where('message_delivery_id', $delivery->id)->count(),
                    ]);
                } else
                {
                    Log::warning('🔗 No URL provided for click event', ['delivery_id' => $delivery->id]);
                }
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
            if ($delivery)
            {
                $delivery->update([
                    'status_id' => 5,  // 5 = complained
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
            if ($delivery)
            {
                $delivery->update([
                    'status_id' => 6,  // 6 = permanent failure
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
            if ($delivery)
            {
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
            if ($delivery)
            {
                $delivery->update([
                    'status_id' => 6,  // 6 = failed
                    'bounced_at' => now(),
                ]);
            }
            break;

        case 'bounced':
            Log::warning("⚠️ EMAIL BOUNCED from {$recipient}", [
                'error' => $request->input('error'),
            ]);
            if ($delivery)
            {
                $delivery->update([
                    'status_id' => 6,  // 6 = bounced
                    'bounced_at' => now(),
                ]);
            }
            break;

        case 'dropped':
            Log::warning("🗑️ EMAIL DROPPED to {$recipient}", [
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
            ]);
            if ($delivery)
            {
                $delivery->update([
                    'status_id' => 6,  // 6 = dropped
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
    if ($delivery && in_array($event, ['delivered', 'opened', 'clicked', 'permanent_fail', 'failed', 'bounced', 'dropped']))
    {
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
            'failed' => $deliveries->whereIn('status_id', [5, 6])->count(),  // complained, failed, bounced
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

Route::group(['prefix' => 'auth'], function ()
{
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function ()
    {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('api.auth.profile.update');
    });
});

// Time reporting by project key only (no API token; developers use HUMANO_PROJECT_KEY in .env)
Route::post('time/store-by-project-key', [TimeController::class, 'storeByProjectKey']);

// Tasks by project key or by context key (project + user); no API token
Route::get('tasks-by-project-key', [TaskController::class, 'tasksByProjectKey']);
Route::get('tasks-by-context-key', [TaskController::class, 'tasksByContextKey']);
Route::post('task-assign-and-start', [TaskController::class, 'taskAssignAndStart']);
Route::post('task-complete-by-context-key', [TaskController::class, 'taskCompleteByContextKey']);

Route::middleware('auth:sanctum')->group(function ()
{
    // Menu for mobile app (filtered by user permissions and team modules)
    Route::get('menu', [MenuController::class, 'index']);

    // Mobile dashboard summary
    Route::get('dashboard', [DashboardController::class, 'index'])->name('api.dashboard.index');

    // Today / Hoy (calendar events + tasks for the current day)
    Route::get('today', [TodayController::class, 'index'])->name('api.today.index');

    // Users of current team (for IDONEO app)
    Route::get('users', [ApiUserController::class, 'index']);

    // Time tracking / Fichaje
    Route::prefix('time')->group(function ()
    {
        Route::get('/', [TimeController::class, 'index']);
        Route::get('/running', [TimeController::class, 'running']);
        Route::post('/store', [TimeController::class, 'store']);
        Route::post('/start', [TimeController::class, 'start']);
        Route::post('/{id}/stop', [TimeController::class, 'stop']);
    });

    // Attendance / Work shift tracking
    Route::prefix('attendance')->group(function ()
    {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::get('/running', [AttendanceController::class, 'running']);
        Route::post('/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/{id}/clock-out', [AttendanceController::class, 'clockOut']);
        Route::post('/{id}/pause', [AttendanceController::class, 'pause']);
        Route::post('/{id}/resume', [AttendanceController::class, 'resume']);
    });

    // Tasks
    Route::prefix('tasks')->group(function ()
    {
        Route::get('/statuses', [TaskController::class, 'statuses']);
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('/{id}', [TaskController::class, 'show']);
        Route::post('/{id}/start', [TaskController::class, 'start']);
        Route::post('/{id}/stop', [TaskController::class, 'stop']);
        Route::put('/{id}/status', [TaskController::class, 'updateStatus']);
        Route::get('/{id}/time', [TaskController::class, 'time']);
    });

    // Category
    Route::get('category', [CategoryController::class, 'index']);

    // Message
    Route::get('message', [MessageController::class, 'index']);
    Route::get('message/{id}', [MessageController::class, 'show']);

    // Contacts - for user-based authentication (Sanctum tokens)
    Route::get('contacts', [ContactController::class, 'index']);
    Route::get('contacts/stats', [ContactController::class, 'stats']);
    Route::get('contacts/{id}', [ContactController::class, 'show']);

    // Clients (enterprises) — Assistant plan module "clients"
    Route::get('clients', [ClientController::class, 'index'])->name('api.clients.index');
    Route::get('clients/{id}', [ClientController::class, 'show'])->name('api.clients.show');

    // Enterprises — Foundation plan module "enterprises"
    Route::get('enterprises', [\App\Http\Controllers\Api\EnterpriseController::class, 'index']);
    Route::get('enterprises/{id}', [\App\Http\Controllers\Api\EnterpriseController::class, 'show']);

    // Mailbox inbox (synced IMAP emails)
    Route::get('emails', [MailInboxController::class, 'index'])->name('api.emails.index');
    Route::get('emails/{id}', [MailInboxController::class, 'show'])->name('api.emails.show');

    // Recipient notifications (navbar inbox for the authenticated user)
    Route::get('notifications', [NotificationInboxController::class, 'index'])->name('api.notifications.index');
    Route::post('notifications/mark-all-read', [NotificationInboxController::class, 'markAllAsRead'])->name('api.notifications.mark-all-read');
    Route::patch('notifications/{notification}/read', [NotificationInboxController::class, 'markAsRead'])->name('api.notifications.mark-as-read');
    Route::delete('notifications/{notification}', [NotificationInboxController::class, 'dismiss'])->name('api.notifications.dismiss');

    // Payments, Products, Orders - moved to team.token middleware (see below)

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

    // Contents - for user-based authentication (Sanctum tokens)
    Route::apiResource('contents', ContentController::class)->names([
        'index' => 'api.contents.index',
        'store' => 'api.contents.store',
        'show' => 'api.contents.show',
        'update' => 'api.contents.update',
        'destroy' => 'api.contents.destroy',
    ]);

    // WhatsApp conversation list and thread messages (same handlers as web /chat/list, /chat/messages, /chat/send).
    Route::get('chat/whatsapp-list', [ChatController::class, 'getChatList'])->name('api.chat.whatsapp-list');
    Route::get('chat/whatsapp-messages/{phone}', [ChatController::class, 'getMessages'])
        ->where('phone', '[0-9]+')
        ->name('api.chat.whatsapp-messages');
    Route::post('chat/whatsapp-send', [ChatController::class, 'sendMessage'])->name('api.chat.whatsapp-send');
    Route::patch('chat/whatsapp-contact-assistant', [ChatController::class, 'updateWhatsAppContactAssistant'])->name('api.chat.whatsapp-contact-assistant');

    // Assistant chat (Sanctum): uses authenticated user's current_team_id (e.g. Asperger Guard).
    Route::post('assistant/chat', [UserAssistantController::class, 'chat'])->name('api.assistant.chat');
    Route::get('assistant/history', [ChatController::class, 'assistantHistory'])->name('api.assistant.history');
    Route::post('assistant/reset-context', [ChatController::class, 'resetAssistantContext'])->name('api.assistant.reset-context');
});

Route::post('/register-application', [LicenseController::class, 'register']);

Route::get('/roles-permissions', [RolePermissionController::class, 'index']);

// Team API routes protected by team token
Route::middleware('team.token')->prefix('team')->group(function ()
{
    // Team information
    Route::get('/', [TeamController::class, 'index']);

    // List prompts available for the team (module_prompts, modules enabled for team)
    Route::get('prompts', [TeamPromptController::class, 'list'])->name('api.team.prompts.list');
    // Invoke prompt by prompt_id (DB) or prompt_name (file); body: test_message required
    Route::post('prompt', TeamPromptController::class)->name('api.team.prompt');
    // Assistant chat (router + flows); body: message required, optional prompt_key
    Route::post('assistant/chat', [TeamAssistantController::class, 'chat'])->name('api.team.assistant.chat');
    Route::get('/settings', [TeamController::class, 'settings']);

    // Team contacts
    Route::resource('contacts', TeamContactController::class);

    // Team projects
    Route::resource('projects', TeamProjectController::class);

    // Team contents
    Route::apiResource('contents', TeamContentController::class)->names([
        'index' => 'api.team.contents.index',
        'store' => 'api.team.contents.store',
        'show' => 'api.team.contents.show',
        'update' => 'api.team.contents.update',
        'destroy' => 'api.team.contents.destroy',
    ]);

    // Team enterprises
    Route::resource('enterprises', TeamEnterpriseController::class);

    // Team payments
    Route::resource('payments', TeamPaymentController::class)->names([
        'index' => 'api.team.payments.index',
        'show' => 'api.team.payments.show',
    ]);

    // Team products
    Route::resource('products', TeamProductController::class)->names([
        'index' => 'api.team.products.index',
        'show' => 'api.team.products.show',
    ]);

    // Team orders
    Route::resource('orders', TeamOrderController::class)->names([
        'index' => 'api.team.orders.index',
        'show' => 'api.team.orders.show',
    ]);
});

// Additional routes with team.token middleware but without /team prefix
Route::middleware('team.token')->group(function ()
{
    // Payments - available at /api/payments (using team token)
    Route::get('payments', [TeamPaymentController::class, 'index'])->name('api.payments.index');
    Route::get('payments/{id}', [TeamPaymentController::class, 'show'])->name('api.payments.show');

    // Products - available at /api/products (using team token)
    Route::get('products', [TeamProductController::class, 'index'])->name('api.products.index');
    Route::get('products/{id}', [TeamProductController::class, 'show'])->name('api.products.show');

    // Orders - available at /api/orders (using team token)
    Route::get('orders', [TeamOrderController::class, 'index'])->name('api.orders.index');
    Route::get('orders/{id}', [TeamOrderController::class, 'show'])->name('api.orders.show');
});

Route::get('/fetch-html', [TemplateImportController::class, 'fetchHtml']);

// Public statistics route (no authentication required)
Route::get('/collaborator/service-statistics', [App\Http\Controllers\Api\CollaboratorController::class, 'getServiceStatistics']);

// Avatar generation endpoint (no authentication required)
Route::get('/avatar', function (Request $request)
{
    $name = $request->input('name', 'User');
    $size = (int) $request->input('size', 100);

    // Validate size to prevent abuse
    $size = max(16, min($size, 500));

    $avatar = \App\Helpers\AvatarHelper::generate($name, $size);

    return response()->json(['avatar' => $avatar]);
});
