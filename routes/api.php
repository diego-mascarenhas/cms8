<?php

use App\Http\Controllers\Api\AdPlatformConnectionController as ApiAdPlatformConnectionController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\AppFeedbackController;
use App\Http\Controllers\Api\AssistantCommercialStatsController;
use App\Http\Controllers\Api\AssistantProductImportController;
use App\Http\Controllers\Api\AssistantSubscriptionController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BillingController as ApiBillingController;
use App\Http\Controllers\Api\BusinessProfileController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificationController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LandingEmbedDemoController;
use App\Http\Controllers\Api\LanguageVariantController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\MailerLookupController;
use App\Http\Controllers\Api\MailInboxController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationInboxController;
use App\Http\Controllers\Api\PaidAdAudienceController as ApiPaidAdAudienceController;
use App\Http\Controllers\Api\PaidAdCampaignController as ApiPaidAdCampaignController;
use App\Http\Controllers\Api\PaidAdCreativeAssetController as ApiPaidAdCreativeAssetController;
use App\Http\Controllers\Api\PaidAdDashboardController as ApiPaidAdDashboardController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFunnelController;
use App\Http\Controllers\Api\PublicAutomationEmbedController;
use App\Http\Controllers\Api\PublicPostController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\Shop\CategoryController as ShopCategoryController;
use App\Http\Controllers\Api\Shop\DashboardController as ShopDashboardController;
use App\Http\Controllers\Api\Shop\LookupController as ShopLookupController;
use App\Http\Controllers\Api\Shop\OrderController as ShopOrderController;
use App\Http\Controllers\Api\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Api\Shop\StoreController as ShopStoreController;
use App\Http\Controllers\Api\SiteAssistantPromptController;
use App\Http\Controllers\Api\SoftwareController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamAssistantController;
use App\Http\Controllers\Api\TeamContactController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamEnterpriseController;
use App\Http\Controllers\Api\TeamOrderController;
use App\Http\Controllers\Api\TeamPaymentController;
use App\Http\Controllers\Api\TeamPostController;
use App\Http\Controllers\Api\TeamProductController;
use App\Http\Controllers\Api\TeamProjectController;
use App\Http\Controllers\Api\TeamPromptController;
use App\Http\Controllers\Api\TeamWhatsAppController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateImportController;
use App\Http\Controllers\Api\TimeController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\UserAssistantController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\WordPressCmsWebhookController;
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

Route::middleware('auth.api')->get('/user', function (Request $request)
{
    return $request->user();
});

Route::prefix('embed/demo')->middleware('throttle:60,1')->group(function ()
{
    Route::get('calendar', [LandingEmbedDemoController::class, 'calendar']);
    Route::post('assistant', [LandingEmbedDemoController::class, 'assistant']);
});

Route::prefix('embed/automation/{token}')->middleware('throttle:60,1')->group(function ()
{
    Route::get('/', [PublicAutomationEmbedController::class, 'meta'])->name('api.embed.automation.meta');
    Route::post('chat', [PublicAutomationEmbedController::class, 'chat'])->name('api.embed.automation.chat');
    // Alias matching cms8-widgets.js fetch(base + '/assistant')
    Route::post('assistant', [PublicAutomationEmbedController::class, 'chat'])->name('api.embed.automation.assistant');
});

// Public CMS read API (anonymous, resolved by team slug; opt-in via cms_public_enabled)
Route::prefix('public/{teamSlug}')->middleware('throttle:120,1')->group(function ()
{
    Route::get('posts', [PublicPostController::class, 'index'])->name('api.public.posts.index');
    Route::get('posts/{postType}/{slug}', [PublicPostController::class, 'show'])->name('api.public.posts.show');
});

// Public projects quote funnel (idoneo-projects SPA) — no prices exposed to client
// Status polling needs a higher ceiling than mutating/AI endpoints.
Route::prefix('projects/funnel')->group(function ()
{
    Route::middleware('throttle:60,1')->group(function ()
    {
        Route::get('requirements', [ProjectFunnelController::class, 'requirements'])->name('api.projects.funnel.requirements');
        Route::get('strategy-tips', [ProjectFunnelController::class, 'strategyTips'])->name('api.projects.funnel.strategy-tips');
        Route::get('quote/status', [ProjectFunnelController::class, 'quoteStatus'])->name('api.projects.funnel.quote.status');
    });

    Route::middleware('throttle:30,1')->group(function ()
    {
        Route::post('lead', [ProjectFunnelController::class, 'lead'])->name('api.projects.funnel.lead');
        Route::post('chat', [ProjectFunnelController::class, 'chat'])->name('api.projects.funnel.chat');
        Route::post('guide', [ProjectFunnelController::class, 'guide'])->name('api.projects.funnel.guide');
        Route::post('quote', [ProjectFunnelController::class, 'quote'])->name('api.projects.funnel.quote');
        Route::post('submit', [ProjectFunnelController::class, 'submit'])->name('api.projects.funnel.submit');
    });
});

// WordPress CMS sync webhook (authenticated by per-team shared secret)
Route::post('wordpress/webhook/{team}', WordPressCmsWebhookController::class)
    ->middleware('throttle:240,1')
    ->name('api.wordpress.cms.webhook');

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
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

    Route::middleware('auth.api')->group(function ()
    {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('api.auth.profile.update');
        Route::get('profile-photo', [AuthController::class, 'showProfilePhoto'])->name('api.auth.profile-photo.show');
        Route::post('profile-photo', [AuthController::class, 'updateProfilePhoto'])->name('api.auth.profile-photo.update');
        Route::delete('profile-photo', [AuthController::class, 'deleteProfilePhoto'])->name('api.auth.profile-photo.destroy');
        Route::put('password', [AuthController::class, 'updatePassword'])->name('api.auth.password.update');
    });
});

// Time reporting by project key only (no API token; developers use HUMANO_PROJECT_KEY in .env)
Route::post('time/store-by-project-key', [TimeController::class, 'storeByProjectKey']);

// Tasks by project key or by context key (project + user); no API token
Route::get('tasks-by-project-key', [TaskController::class, 'tasksByProjectKey']);
Route::get('tasks-by-context-key', [TaskController::class, 'tasksByContextKey']);
Route::post('task-assign-and-start', [TaskController::class, 'taskAssignAndStart']);
Route::post('task-complete-by-context-key', [TaskController::class, 'taskCompleteByContextKey']);

Route::middleware('auth.api')->group(function ()
{
    // Menu for mobile app (filtered by user permissions and team modules)
    Route::get('menu', [MenuController::class, 'index']);

    Route::get('billing', [ApiBillingController::class, 'show'])->name('api.billing.show');
    Route::put('billing', [ApiBillingController::class, 'update'])->name('api.billing.update');
    Route::get('team/business-profile', [BusinessProfileController::class, 'show'])->name('api.team.business-profile.show');
    Route::put('team/business-profile', [BusinessProfileController::class, 'update'])->name('api.team.business-profile.update');
    Route::get('team/business-profile/assets', [BusinessProfileController::class, 'showAsset'])->name('api.team.business-profile.assets.show');
    Route::post('team/business-profile/assets', [BusinessProfileController::class, 'storeAsset'])->name('api.team.business-profile.assets.store');
    Route::delete('team/business-profile/assets', [BusinessProfileController::class, 'destroyAsset'])->name('api.team.business-profile.assets.destroy');
    Route::post('team/business-profile/summary', [BusinessProfileController::class, 'generateSummary'])->name('api.team.business-profile.summary');
    Route::post('team/business-profile/insights', [BusinessProfileController::class, 'queueInsights'])->name('api.team.business-profile.insights');
    Route::get('assistant/subscription', [AssistantSubscriptionController::class, 'show'])->name('api.assistant.subscription.show');
    Route::post('assistant/subscription/cancel', [AssistantSubscriptionController::class, 'cancel'])->name('api.assistant.subscription.cancel');
    Route::post('assistant/subscription/resume', [AssistantSubscriptionController::class, 'resume'])->name('api.assistant.subscription.resume');
    Route::post('assistant/checkout', [AssistantSubscriptionController::class, 'checkout'])->name('api.assistant.checkout');
    Route::post('assistant/checkout/complete', [AssistantSubscriptionController::class, 'complete'])->name('api.assistant.checkout.complete');
    Route::post('assistant/payment-method', [AssistantSubscriptionController::class, 'paymentMethod'])->name('api.assistant.payment-method');
    Route::get('assistant/site-prompt', [SiteAssistantPromptController::class, 'show'])->name('api.assistant.site-prompt.show');
    Route::put('assistant/site-prompt', [SiteAssistantPromptController::class, 'update'])->name('api.assistant.site-prompt.update');
    Route::patch('assistant/site-prompt', [SiteAssistantPromptController::class, 'updateContent'])->name('api.assistant.site-prompt.content');
    Route::post('assistant/site-prompt', [SiteAssistantPromptController::class, 'store'])->name('api.assistant.site-prompt.store');
    Route::post('assistant/site-prompt/from-catalog', [SiteAssistantPromptController::class, 'applyCatalog'])->name('api.assistant.site-prompt.from-catalog');
    Route::get('assistant/commercial-stats', [AssistantCommercialStatsController::class, 'show'])->name('api.assistant.commercial-stats.show');
    Route::get('assistant/products/import', [AssistantProductImportController::class, 'show'])->name('api.assistant.products.import.show');
    Route::get('assistant/products/import/sample', [AssistantProductImportController::class, 'sample'])->name('api.assistant.products.import.sample');
    Route::post('assistant/products/import', [AssistantProductImportController::class, 'store'])->name('api.assistant.products.import.store');

    // Mobile dashboard summary
    Route::get('dashboard', [DashboardController::class, 'index'])->name('api.dashboard.index');

    // Today / Hoy (calendar events + tasks for the current day)
    Route::get('today', [TodayController::class, 'index'])->name('api.today.index');

    // Users of current team (for IDONEO app)
    Route::get('users', [ApiUserController::class, 'index']);
    Route::post('users', [ApiUserController::class, 'store']);
    Route::delete('users/{user}', [ApiUserController::class, 'destroy']);

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
        Route::get('/summary', [TaskController::class, 'summary']);
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('/{id}', [TaskController::class, 'show']);
        Route::put('/{id}', [TaskController::class, 'update']);
        Route::delete('/{id}', [TaskController::class, 'destroy']);
        Route::post('/{id}/start', [TaskController::class, 'start']);
        Route::post('/{id}/stop', [TaskController::class, 'stop']);
        Route::put('/{id}/status', [TaskController::class, 'updateStatus']);
        Route::get('/{id}/time', [TaskController::class, 'time']);
    });

    // Category
    Route::get('category', [CategoryController::class, 'index']);

    // Message / Mailer (idoneo-mailer SPA)
    Route::get('message', [MessageController::class, 'index']);
    Route::post('message', [MessageController::class, 'store']);
    Route::get('message/{id}', [MessageController::class, 'show'])->whereNumber('id');
    Route::put('message/{id}', [MessageController::class, 'update'])->whereNumber('id');
    Route::delete('message/{id}', [MessageController::class, 'destroy'])->whereNumber('id');
    Route::post('message/{id}/start', [MessageController::class, 'start'])->whereNumber('id');
    Route::post('message/{id}/pause', [MessageController::class, 'pause'])->whereNumber('id');
    Route::post('message/{id}/test', [MessageController::class, 'test'])->whereNumber('id');
    Route::get('message/{id}/preview', [MessageController::class, 'preview'])->whereNumber('id');
    Route::get('mailer/lookups', [MailerLookupController::class, 'index']);

    // Templates (idoneo-mailer SPA)
    Route::get('templates', [TemplateController::class, 'index']);
    Route::post('templates', [TemplateController::class, 'store']);
    Route::get('templates/{id}', [TemplateController::class, 'show'])->whereNumber('id');
    Route::put('templates/{id}', [TemplateController::class, 'update'])->whereNumber('id');
    Route::delete('templates/{id}', [TemplateController::class, 'destroy'])->whereNumber('id');
    Route::post('templates/{id}/duplicate', [TemplateController::class, 'duplicate'])->whereNumber('id');

    // Affiliates (idoneo-affiliates SPA)
    Route::get('affiliates/dashboard', [AffiliateController::class, 'dashboard']);
    Route::post('affiliates/setup-stripe', [AffiliateController::class, 'setupStripe']);
    Route::post('affiliates/invitations', [AffiliateController::class, 'invite']);
    Route::post('affiliates/claim', [AffiliateController::class, 'claim']);
    Route::post('feedback', [AppFeedbackController::class, 'store']);

    // Paid Ads (idoneo-ads SPA)
    Route::get('paid-ads/dashboard', [ApiPaidAdDashboardController::class, 'index']);
    Route::get('paid-ads/lookups', [ApiPaidAdCampaignController::class, 'lookups']);
    Route::get('paid-ads/calendar', [ApiPaidAdCampaignController::class, 'calendar']);
    Route::get('paid-ads/assets', [ApiPaidAdCreativeAssetController::class, 'show']);
    Route::post('paid-ads/assets', [ApiPaidAdCreativeAssetController::class, 'store']);
    Route::delete('paid-ads/assets', [ApiPaidAdCreativeAssetController::class, 'destroy']);
    Route::post('paid-ads/suggest-copy', [ApiPaidAdCampaignController::class, 'suggestCopy']);
    Route::post('paid-ads/suggest-image', [ApiPaidAdCampaignController::class, 'suggestImage']);
    Route::post('paid-ads/generate-image', [ApiPaidAdCampaignController::class, 'generateImage']);
    Route::get('paid-ads/connections', [ApiAdPlatformConnectionController::class, 'index']);
    Route::post('paid-ads/connections/{platform}/authorize', [ApiAdPlatformConnectionController::class, 'authorizeUrl']);
    Route::post('paid-ads/connections/{id}/select-account', [ApiAdPlatformConnectionController::class, 'selectAccount'])->whereNumber('id');
    Route::delete('paid-ads/connections/{id}', [ApiAdPlatformConnectionController::class, 'destroy'])->whereNumber('id');
    Route::get('paid-ads/audiences', [ApiPaidAdAudienceController::class, 'index']);
    Route::post('paid-ads/audiences', [ApiPaidAdAudienceController::class, 'store']);
    Route::get('paid-ads/audiences/{id}', [ApiPaidAdAudienceController::class, 'show'])->whereNumber('id');
    Route::put('paid-ads/audiences/{id}', [ApiPaidAdAudienceController::class, 'update'])->whereNumber('id');
    Route::delete('paid-ads/audiences/{id}', [ApiPaidAdAudienceController::class, 'destroy'])->whereNumber('id');
    Route::get('paid-ads', [ApiPaidAdCampaignController::class, 'index']);
    Route::post('paid-ads', [ApiPaidAdCampaignController::class, 'store']);
    Route::get('paid-ads/{id}', [ApiPaidAdCampaignController::class, 'show'])->whereNumber('id');
    Route::put('paid-ads/{id}', [ApiPaidAdCampaignController::class, 'update'])->whereNumber('id');
    Route::delete('paid-ads/{id}', [ApiPaidAdCampaignController::class, 'destroy'])->whereNumber('id');
    Route::post('paid-ads/{id}/publish', [ApiPaidAdCampaignController::class, 'publish'])->whereNumber('id');
    Route::post('paid-ads/{id}/pause', [ApiPaidAdCampaignController::class, 'pause'])->whereNumber('id');
    Route::post('paid-ads/{id}/resume', [ApiPaidAdCampaignController::class, 'resume'])->whereNumber('id');
    Route::post('paid-ads/{id}/sync-metrics', [ApiPaidAdCampaignController::class, 'syncMetrics'])->whereNumber('id');

    // Shop (idoneo-shop SPA)
    Route::prefix('shop')->group(function ()
    {
        Route::get('lookups', [ShopLookupController::class, 'index']);
        Route::get('dashboard', [ShopDashboardController::class, 'index']);
        Route::post('categories', [ShopCategoryController::class, 'store']);

        Route::get('products/import', [ShopProductController::class, 'importSchema']);
        Route::post('products/import', [ShopProductController::class, 'import']);
        Route::get('products', [ShopProductController::class, 'index']);
        Route::post('products', [ShopProductController::class, 'store']);
        Route::get('products/{id}', [ShopProductController::class, 'show'])->whereNumber('id');
        Route::put('products/{id}', [ShopProductController::class, 'update'])->whereNumber('id');
        Route::delete('products/{id}', [ShopProductController::class, 'destroy'])->whereNumber('id');

        Route::get('stores', [ShopStoreController::class, 'index']);
        Route::post('stores', [ShopStoreController::class, 'store']);
        Route::get('stores/{id}', [ShopStoreController::class, 'show'])->whereNumber('id');
        Route::put('stores/{id}', [ShopStoreController::class, 'update'])->whereNumber('id');
        Route::delete('stores/{id}', [ShopStoreController::class, 'destroy'])->whereNumber('id');

        Route::get('orders', [ShopOrderController::class, 'index']);
        Route::get('orders/{id}', [ShopOrderController::class, 'show'])->whereNumber('id');
        Route::put('orders/{id}', [ShopOrderController::class, 'update'])->whereNumber('id');
    });

    // Contacts - for user-based authentication (Sanctum tokens)
    Route::get('contacts', [ContactController::class, 'index']);
    Route::get('contacts/stats', [ContactController::class, 'stats']);
    Route::get('contacts/{id}', [ContactController::class, 'show']);

    // Clients (enterprises) — Assistant plan module "clients"
    Route::get('clients', [ClientController::class, 'index'])->name('api.clients.index');
    Route::get('clients/{id}', [ClientController::class, 'show'])->name('api.clients.show');

    // Enterprises — Mentor plan module "enterprises"
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

    // Projects - for user-based authentication (Sanctum tokens) / idoneo-projects SPA
    Route::get('project-statuses', [ProjectController::class, 'statuses']);
    Route::get('projects/stats', [ProjectController::class, 'stats']);
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{id}', [ProjectController::class, 'show']);
    Route::put('projects/{id}', [ProjectController::class, 'update']);
    Route::patch('projects/{id}', [ProjectController::class, 'update']);
    Route::delete('projects/{id}', [ProjectController::class, 'destroy']);
    Route::get('projects/{id}/board', [ProjectController::class, 'board']);
    Route::put('projects/{id}/board/reorder', [ProjectController::class, 'reorderBoard']);

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

    // WhatsApp inbox (idoneo-assistant SPA) — same handlers as web /chat/list, /chat/messages, /chat/send, /chat/whatsapp-status.
    Route::get('chat/whatsapp-list', [ChatController::class, 'getChatList'])->name('api.chat.whatsapp-list');
    Route::get('chat/whatsapp-messages/{phone}', [ChatController::class, 'getMessages'])
        ->where('phone', '[0-9]+')
        ->name('api.chat.whatsapp-messages');
    Route::post('chat/whatsapp-send', [ChatController::class, 'sendMessage'])->name('api.chat.whatsapp-send');
    Route::post('chat/schedule-message', [ChatController::class, 'scheduleMessage'])->name('api.chat.schedule-message');
    Route::patch('chat/scheduled-message/{scheduledMessage}', [ChatController::class, 'updateScheduledMessage'])->name('api.chat.scheduled-message.update');
    Route::delete('chat/scheduled-message/{scheduledMessage}', [ChatController::class, 'destroyScheduledMessage'])->name('api.chat.scheduled-message.destroy');
    Route::get('chat/whatsapp-search-contacts', [ChatController::class, 'searchWhatsAppContacts'])->name('api.chat.whatsapp-search-contacts');
    Route::post('chat/whatsapp-start-contact', [ChatController::class, 'startWhatsAppContact'])->name('api.chat.whatsapp-start-contact');
    Route::patch('chat/whatsapp-contact', [ChatController::class, 'updateWhatsAppInboxContact'])->name('api.chat.whatsapp-contact');
    Route::patch('chat/whatsapp-archive', [ChatController::class, 'updateWhatsAppChatArchive'])->name('api.chat.whatsapp-archive');
    Route::patch('chat/whatsapp-read', [ChatController::class, 'updateWhatsAppChatRead'])->name('api.chat.whatsapp-read');
    Route::patch('chat/whatsapp-contact-assistant', [ChatController::class, 'updateWhatsAppContactAssistant'])->name('api.chat.whatsapp-contact-assistant');
    Route::patch('chat/whatsapp-contact-categories', [ChatController::class, 'updateWhatsAppContactCategories'])->name('api.chat.whatsapp-contact-categories');
    Route::post('chat/whatsapp-contact-categories', [ChatController::class, 'storeWhatsAppContactCategory'])->name('api.chat.whatsapp-contact-categories.store');
    Route::get('chat/whatsapp-status', [ChatController::class, 'whatsappStatus'])->name('api.chat.whatsapp-status');
    Route::get('chat/whatsapp-qr-image', [ChatController::class, 'whatsappQrImage'])->name('api.chat.whatsapp-qr-image');
    Route::post('chat/whatsapp-refresh-qr', [ChatController::class, 'whatsappRefreshQr'])->name('api.chat.whatsapp-refresh-qr');
    Route::post('chat/whatsapp-warmup-qr', [ChatController::class, 'whatsappWarmupQr'])->name('api.chat.whatsapp-warmup-qr');
    Route::post('chat/whatsapp-disconnect', [ChatController::class, 'whatsappDisconnect'])->name('api.chat.whatsapp-disconnect');

    // Assistant chat (idoneo-assistant SPA / Sanctum): uses authenticated user's current_team_id.
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
    Route::post('whatsapp/send', [TeamWhatsAppController::class, 'send'])->name('api.team.whatsapp.send');
    Route::get('/settings', [TeamController::class, 'settings']);

    // Team contacts
    Route::resource('contacts', TeamContactController::class);

    // Team projects
    Route::resource('projects', TeamProjectController::class);

    // Team CMS posts (WordPress-like)
    Route::get('posts', [TeamPostController::class, 'index'])->name('api.team.posts.index');
    Route::post('posts', [TeamPostController::class, 'store'])->name('api.team.posts.store');
    Route::get('posts/{id}', [TeamPostController::class, 'show'])->whereNumber('id')->name('api.team.posts.show');
    Route::match(['put', 'patch'], 'posts/{id}', [TeamPostController::class, 'update'])->whereNumber('id')->name('api.team.posts.update');
    Route::delete('posts/{id}', [TeamPostController::class, 'destroy'])->whereNumber('id')->name('api.team.posts.destroy');

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
