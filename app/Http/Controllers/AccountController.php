<?php

namespace App\Http\Controllers;

use App\DataTables\AccountDataTable;
use App\DataTables\AccountRatesUsageDataTable;
use App\Enums\TeamBillingFrequency;
use App\Enums\TeamBillingProduct;
use App\Helpers\TokenHelper;
use App\Http\Requests\UpdateAccountRatesRequest;
use App\Mail\AutologinInvitationMail;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Services\TeamBillingUsageSummaryService;
use App\Support\TeamUsageInvoiceFrequency;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountController extends Controller
{
    use ConfiguresTeamMail;

    /**
     * Additional module keys not shown on the root account edit form (state is preserved on save).
     *
     * @return list<string>
     */
    protected function moduleKeysHiddenFromAccountForm(): array
    {
        return ['accounting', 'events'];
    }

    public function index(AccountDataTable $dataTable)
    {
        return $dataTable->render('account.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $team = Team::findOrFail($id);
        $coreModules = Module::where('is_core', true)
            ->orderBy('name')
            ->get();

        // Group additional modules by their 'group' field (omit keys managed outside this form)
        $hiddenKeys = $this->moduleKeysHiddenFromAccountForm();
        $additionalModules = Module::where('is_core', false)
            ->orderBy('group')
            ->orderBy('order')
            ->get()
            ->groupBy('group')
            ->map(static function (Collection $modules) use ($hiddenKeys): Collection
            {
                return $modules->whereNotIn('key', $hiddenKeys)->values();
            })
            ->filter(static fn (Collection $modules): bool => $modules->isNotEmpty());

        // Define group labels for better UI
        $groupLabels = [
            'billing' => ['name' => 'Accounting', 'icon' => 'calculator', 'description' => 'Subscriptions, invoices, payments, affiliates and financial modules'],
            'ecommerce' => ['name' => 'E-commerce', 'icon' => 'shopping-cart', 'description' => 'E-commerce module (stores, products, orders)'],
            'infrastructure' => ['name' => 'Infrastructure', 'icon' => 'server', 'description' => 'Infrastructure management (servers, hosting)'],
            'campaigns' => ['name' => 'Marketing', 'icon' => 'broadcast', 'description' => 'Campaign messages, Mailer, templates and scheduled sends (email, WhatsApp, etc.)'],
            'automation' => ['name' => 'Automation', 'icon' => 'robot', 'description' => 'Assistant instructions, funnel and API.'],
            'innovation' => ['name' => 'Innovation', 'icon' => 'bulb', 'description' => 'Ideas, proposals and innovation challenges'],
            'security' => ['name' => 'Security', 'icon' => 'shield-lock', 'description' => 'Passwords and canary token security tools'],
            'content' => ['name' => 'Content', 'icon' => 'photo', 'description' => 'Content, multimedia, blog, e-books, academy and landing pages'],
            'support' => ['name' => 'Support', 'icon' => 'headset', 'description' => 'Customer support (tickets, mailbox, chat)'],
            'learning' => ['name' => 'Learning & Development', 'icon' => 'book', 'description' => 'Languages, certifications and training'],
            '' => ['name' => 'General Management', 'icon' => 'briefcase', 'description' => 'General management modules'],
        ];

        foreach ($additionalModules->keys() as $groupKey)
        {
            if ($groupKey === '' || isset($groupLabels[$groupKey]))
            {
                continue;
            }
            $groupLabels[$groupKey] = [
                'name' => ucfirst(str_replace('_', ' ', (string) $groupKey)),
                'icon' => 'layout-grid',
                'description' => '',
            ];
        }

        return view('account.form', compact(
            'team',
            'coreModules',
            'additionalModules',
            'groupLabels',
        ));
    }

    public function editRates(string $id, AccountRatesUsageDataTable $dataTable, TeamBillingUsageSummaryService $usage)
    {
        $team = Team::findOrFail($id);
        $billingRates = $this->billingRatesForForm($team);
        $billingRateHistory = TeamBillingRate::query()
            ->where('team_id', $team->id)
            ->orderByDesc('effective_from')
            ->limit(12)
            ->get();
        $invoiceFrequency = TeamUsageInvoiceFrequency::for($team);
        $invoicePreview = $usage->invoicePreview($team, $invoiceFrequency);

        return $dataTable->render('account.rates', compact(
            'team',
            'billingRates',
            'billingRateHistory',
            'invoiceFrequency',
            'invoicePreview',
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = Team::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'modules' => 'array',
            'modules.*' => 'string|exists:modules,key',
        ]);

        $team->update([
            'name' => $request->name,
        ]);

        // Get all modules (core and additional)
        $allModules = Module::all();

        $hiddenKeys = $this->moduleKeysHiddenFromAccountForm();
        $preserveEnabledKeys = [];
        foreach ($hiddenKeys as $hiddenKey)
        {
            if ($team->hasModule($hiddenKey))
            {
                $preserveEnabledKeys[] = $hiddenKey;
            }
        }

        // Disable all modules first
        foreach ($allModules as $module)
        {
            $team->disableModule($module->key);
        }

        // Enable selected modules
        if ($request->has('modules'))
        {
            foreach ($request->modules as $moduleKey)
            {
                $team->enableModule($moduleKey);
            }
        }

        foreach ($preserveEnabledKeys as $moduleKey)
        {
            $team->enableModule($moduleKey);
        }

        // Clear menu cache for all users in this team
        $this->clearTeamMenuCache($team);

        return redirect()
            ->route('account-management')
            ->with('success', 'Account updated successfully');
    }

    public function updateRates(UpdateAccountRatesRequest $request, string $id)
    {
        $team = Team::findOrFail($id);
        $this->syncTeamBillingRates($team, $request);
        TeamUsageInvoiceFrequency::set(
            $team,
            TeamBillingFrequency::from($request->validated('invoice_frequency')),
        );

        return redirect()
            ->route('account.rates.edit', $team->id)
            ->with('success', 'Tarifas actualizadas');
    }

    public function updateOwnerPassword(Request $request, string $id)
    {
        $team = Team::query()->with('owner')->findOrFail($id);

        if (! $team->owner)
        {
            return response()->json([
                'success' => false,
                'message' => 'La cuenta no tiene propietario asignado',
            ], 400);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $team->owner->update([
            'password' => Hash::make($validated['password']),
        ]);

        Log::info('Account owner password updated', [
            'team_id' => $team->id,
            'owner_id' => $team->owner->id,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }

    /**
     * Clear menu cache for all users in a team
     */
    private function clearTeamMenuCache(Team $team)
    {
        // Clear cache for all users in this team
        foreach ($team->users as $user)
        {
            $cacheKey = "menu_user_{$user->id}_team_{$team->id}";
            Cache::forget($cacheKey);
        }
    }

    /**
     * Show subscriptions for a specific account.
     */
    public function showSubscriptions(string $id)
    {
        $team = Team::with(['subscriptions.team.owner'])->findOrFail($id);

        // Get products for each subscription to display names
        $subscriptionsWithProducts = $team->subscriptions->map(function ($subscription)
        {
            $product = \App\Models\SubscriptionProduct::where('stripe_price', $subscription->stripe_price)->first();
            $subscription->product = $product;

            // Ensure team relationship is loaded
            if (! $subscription->relationLoaded('team'))
            {
                $subscription->load('team.owner');
            }

            // Get next billing date from Stripe
            $nextBillingDate = null;
            try
            {
                if ($subscription->stripe_id)
                {
                    $stripeSubscription = $subscription->asStripeSubscription();
                    if ($stripeSubscription && isset($stripeSubscription->current_period_end))
                    {
                        $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                    }
                }
            } catch (\Exception $e)
            {
                \Log::warning('Error fetching Stripe subscription data', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'error' => $e->getMessage(),
                ]);
            }
            $subscription->nextBillingDate = $nextBillingDate;

            // Find pending SLA acceptance for this subscription
            $pendingAcceptance = \App\Models\SLAAcceptance::where('subscription_id', $subscription->id)
                ->whereNull('accepted_at')
                ->with(['sla.subscriptionProduct'])
                ->first();

            // Find accepted SLA acceptance for this subscription
            // First try by subscription_id, then by product and team email
            $acceptedSLA = \App\Models\SLAAcceptance::where('subscription_id', $subscription->id)
                ->whereNotNull('accepted_at')
                ->with(['sla.subscriptionProduct'])
                ->latest('accepted_at')
                ->first();

            // If not found by subscription_id, try to find by product and team owner email
            if (! $acceptedSLA && $product && $subscription->team)
            {
                // Load team owner if not loaded
                if (! $subscription->team->relationLoaded('owner'))
                {
                    $subscription->team->load('owner');
                }

                if ($subscription->team->owner)
                {
                    $acceptedSLA = \App\Models\SLAAcceptance::whereHas('sla.subscriptionProduct', function ($q) use ($product)
                    {
                        $q->where('id', $product->id);
                    })
                        ->where('accepted_by_email', $subscription->team->owner->email)
                        ->whereNotNull('accepted_at')
                        ->with(['sla.subscriptionProduct'])
                        ->latest('accepted_at')
                        ->first();
                }
            }

            // Find products with SLA for this subscription
            // Only show "Enviar SLA" if there's no pending acceptance AND no accepted SLA
            $productForSending = null;
            if (! $pendingAcceptance && ! $acceptedSLA && $product)
            {
                // Check if the product has an SLA
                $productWithSLA = \App\Models\SubscriptionProduct::where('id', $product->id)
                    ->whereHas('sla', function ($q)
                    {
                        $q->where('is_active', true);
                    })
                    ->with(['sla.acceptances' => function ($q) use ($subscription)
                    {
                        $q->whereNull('accepted_at')
                            ->where('subscription_id', $subscription->id);
                    }])
                    ->first();

                if ($productWithSLA && $productWithSLA->sla)
                {
                    // Check if there's a pending acceptance
                    $pendingAcceptance = $productWithSLA->sla->acceptances()
                        ->whereNull('accepted_at')
                        ->where('subscription_id', $subscription->id)
                        ->first();

                    // If no pending acceptance and no accepted SLA, this product can send SLA
                    if (! $pendingAcceptance && ! $acceptedSLA)
                    {
                        $productForSending = $productWithSLA;
                    }
                }
            }

            $subscription->pendingSlaAcceptance = $pendingAcceptance;
            $subscription->acceptedSLA = $acceptedSLA;
            $subscription->productForSendingSLA = $productForSending;

            return $subscription;
        });

        return view('account.subscriptions', [
            'team' => $team,
            'subscriptionsWithProducts' => $subscriptionsWithProducts,
        ]);
    }

    /**
     * Show all subscriptions across all teams.
     */
    public function allSubscriptions()
    {
        // Get all subscriptions with their teams and products
        // Use query builder to ensure we're using the correct model
        $allSubscriptions = Subscription::query()
            ->with(['team' => function ($query)
            {
                $query->with('owner');
            }])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($subscription)
            {
                $product = \App\Models\SubscriptionProduct::where('stripe_price', $subscription->stripe_price)->first();

                // Ensure team relationship is loaded
                if (! $subscription->relationLoaded('team'))
                {
                    $subscription->load(['team' => function ($query)
                    {
                        $query->with('owner');
                    }]);
                }
                $subscription->product = $product;

                // Get next billing date from Stripe
                $nextBillingDate = null;
                try
                {
                    if ($subscription->stripe_id)
                    {
                        $stripeSubscription = $subscription->asStripeSubscription();
                        if ($stripeSubscription && isset($stripeSubscription->current_period_end))
                        {
                            $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                        }
                    }
                } catch (\Exception $e)
                {
                    \Log::warning('Error fetching Stripe subscription data', [
                        'subscription_id' => $subscription->id,
                        'stripe_id' => $subscription->stripe_id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $subscription->nextBillingDate = $nextBillingDate;

                // Find pending SLA acceptance for this subscription
                $pendingAcceptance = \App\Models\SLAAcceptance::where('subscription_id', $subscription->id)
                    ->whereNull('accepted_at')
                    ->with(['sla.subscriptionProduct'])
                    ->first();

                // Find accepted SLA acceptance for this subscription
                // First try by subscription_id, then by product and team email
                $acceptedSLA = \App\Models\SLAAcceptance::where('subscription_id', $subscription->id)
                    ->whereNotNull('accepted_at')
                    ->with(['sla.subscriptionProduct'])
                    ->latest('accepted_at')
                    ->first();

                // If not found by subscription_id, try to find by product and team owner email
                if (! $acceptedSLA && $product && $subscription->team)
                {
                    // Load team owner if not loaded
                    if (! $subscription->team->relationLoaded('owner'))
                    {
                        $subscription->team->load('owner');
                    }

                    if ($subscription->team->owner)
                    {
                        $acceptedSLA = \App\Models\SLAAcceptance::whereHas('sla.subscriptionProduct', function ($q) use ($product)
                        {
                            $q->where('id', $product->id);
                        })
                            ->where('accepted_by_email', $subscription->team->owner->email)
                            ->whereNotNull('accepted_at')
                            ->with(['sla.subscriptionProduct'])
                            ->latest('accepted_at')
                            ->first();
                    }
                }

                // Find products with SLA for this subscription
                $productForSending = null;
                if (! $pendingAcceptance && ! $acceptedSLA && $product)
                {
                    $productWithSLA = \App\Models\SubscriptionProduct::where('id', $product->id)
                        ->whereHas('sla', function ($q)
                        {
                            $q->where('is_active', true);
                        })
                        ->first();

                    if ($productWithSLA && $productWithSLA->sla)
                    {
                        $pendingAcceptance = $productWithSLA->sla->acceptances()
                            ->whereNull('accepted_at')
                            ->where('subscription_id', $subscription->id)
                            ->first();

                        if (! $pendingAcceptance && ! $acceptedSLA)
                        {
                            $productForSending = $productWithSLA;
                        }
                    }
                }

                $subscription->pendingSlaAcceptance = $pendingAcceptance;
                $subscription->acceptedSLA = $acceptedSLA;
                $subscription->productForSendingSLA = $productForSending;

                return $subscription;
            });

        return view('account.all-subscriptions', [
            'allSubscriptions' => $allSubscriptions,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Revoke autologin tokens for account owner
     */
    public function revokeAutologinToken(Request $request, string $id)
    {
        $team = Team::findOrFail($id);

        if (! $team->owner)
        {
            return response()->json([
                'success' => false,
                'message' => 'La cuenta no tiene propietario asignado',
            ], 400);
        }

        if (TokenHelper::revokeUserTokens($team->owner->id, 'account_owner_autologin'))
        {
            return response()->json([
                'success' => true,
                'message' => 'Tokens de autologueo revocados exitosamente. El propietario necesitará un nuevo link.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al revocar los tokens',
        ], 500);
    }

    /**
     * Send autologin invitation email to account owner
     */
    public function sendAutologinInvitation(Request $request, string $id)
    {
        $team = Team::findOrFail($id);

        if (! $team->owner)
        {
            return response()->json([
                'success' => false,
                'message' => 'La cuenta no tiene propietario asignado',
            ], 400);
        }

        if (! $team->owner->email)
        {
            return response()->json([
                'success' => false,
                'message' => 'El propietario no tiene email configurado',
            ], 400);
        }

        try
        {
            // Configure mail for the team (custom SMTP or system with advertising)
            $this->configureMailForTeam($team);

            // Generate autologin token
            $token = TokenHelper::generateSignedToken($team->owner, 'account_owner_autologin', 720); // 30 days
            $loginUrl = route('login.token', ['token' => $token]);
            $fullUrl = url($loginUrl);

            // Send email
            Mail::to($team->owner->email)->send(new AutologinInvitationMail($team->owner, $team, $fullUrl));

            Log::info('Autologin invitation email sent', [
                'user_id' => $team->owner->id,
                'user_email' => $team->owner->email,
                'team_id' => $team->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitación enviada exitosamente al propietario de la cuenta.',
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error sending autologin invitation email', [
                'user_id' => $team->owner->id,
                'team_id' => $team->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la invitación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{tokens_multiplier: string, whatsapp_send: string, mailer_send: string}
     */
    private function billingRatesForForm(Team $team): array
    {
        return [
            'tokens_multiplier' => TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::TokensMultiplier),
            'whatsapp_send' => TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::WhatsappSend),
            'mailer_send' => TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::MailerSend),
        ];
    }

    private function syncTeamBillingRates(Team $team, Request $request): void
    {
        $fields = [
            'tokens_multiplier' => TeamBillingProduct::TokensMultiplier,
            'whatsapp_send' => TeamBillingProduct::WhatsappSend,
            'mailer_send' => TeamBillingProduct::MailerSend,
        ];

        foreach ($fields as $field => $product)
        {
            if (! $request->filled($field))
            {
                continue;
            }

            $amount = (float) $request->input($field);
            $current = TeamBillingRate::amountOn((int) $team->id, $product);
            if (abs($current - $amount) < 0.0000001)
            {
                continue;
            }

            TeamBillingRate::setAmount((int) $team->id, $product, $amount);
        }
    }
}
