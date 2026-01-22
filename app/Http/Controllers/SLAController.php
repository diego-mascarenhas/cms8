<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use App\Mail\SLAAcceptanceMail;
use App\Models\SLA;
use App\Models\SLAAcceptance;
use App\Models\SubscriptionProduct;
use App\Models\Subscription;
use App\Models\Team;
use App\Services\Stripe\StripeSubscriptionService;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SLAController extends Controller
{
    use ConfiguresTeamMail;

    /**
     * Show the form for creating a new SLA
     */
    public function create(Request $request, string $productId)
    {
        $product = SubscriptionProduct::findOrFail($productId);

        $sla = $product->sla;

        return view('sla.form', compact('product', 'sla'));
    }

    /**
     * Store a newly created SLA
     */
    public function store(Request $request, string $productId)
    {
        $product = SubscriptionProduct::findOrFail($productId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10', // Minimum 10 characters to ensure it's not just empty HTML
            'version' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ], [
            'content.required' => 'El campo contenido es obligatorio.',
            'content.min' => 'El contenido del SLA debe tener al menos 10 caracteres.',
        ]);

        // Desactivar otros SLAs activos del mismo producto
        if ($request->input('is_active', true))
        {
            SLA::where('subscription_product_id', $productId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $sla = SLA::create([
            'subscription_product_id' => $productId,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'version' => $validated['version'] ?? '1.0',
            'is_active' => $request->input('is_active', true),
        ]);

        return redirect()
            ->route('account.products.index')
            ->with('success', 'SLA creado exitosamente');
    }

    /**
     * Show the form for editing an SLA
     */
    public function edit(string $productId, string $slaId)
    {
        $product = SubscriptionProduct::findOrFail($productId);

        $sla = SLA::where('subscription_product_id', $productId)
            ->findOrFail($slaId);

        return view('sla.form', compact('product', 'sla'));
    }

    /**
     * Update an existing SLA
     */
    public function update(Request $request, string $productId, string $slaId)
    {
        $product = SubscriptionProduct::findOrFail($productId);

        $sla = SLA::where('subscription_product_id', $productId)
            ->findOrFail($slaId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10', // Minimum 10 characters to ensure it's not just empty HTML
            'version' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ], [
            'content.required' => 'El campo contenido es obligatorio.',
            'content.min' => 'El contenido del SLA debe tener al menos 10 caracteres.',
        ]);

        // Si se activa este SLA, desactivar otros
        if ($request->input('is_active', false) && ! $sla->is_active)
        {
            SLA::where('subscription_product_id', $productId)
                ->where('id', '!=', $slaId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $sla->update($validated);

        return redirect()
            ->route('account.products.index')
            ->with('success', 'SLA actualizado exitosamente');
    }

    /**
     * Send SLA acceptance email to client
     */
    public function sendSLA(Request $request, string $productId)
    {
        $product = SubscriptionProduct::findOrFail($productId);

        $sla = $product->sla;
        if (! $sla)
        {
            return response()->json([
                'success' => false,
                'message' => 'Este producto no tiene un SLA configurado',
            ], 400);
        }

        // Get team email from request or find first team with this product
        $teamId = $request->input('team_id');
        if (! $teamId)
        {
            // Find a team that has a subscription with this product
            $subscription = Subscription::where('stripe_price', $product->stripe_price)
                ->where('stripe_status', 'active')
                ->first();
            if ($subscription)
            {
                $teamId = $subscription->team_id;
            }
        }

        if (! $teamId)
        {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un equipo asociado a este producto',
            ], 400);
        }

        $team = Team::findOrFail($teamId);
        $clientEmail = $team->owner->email ?? null;

        if (! $clientEmail)
        {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene email configurado',
            ], 400);
        }

        try
        {
            // Generate unique token for acceptance
            $token = bin2hex(random_bytes(32));

            // Create acceptance record (pending)
            $acceptance = SLAAcceptance::create([
                'sla_id' => $sla->id,
                'token' => $token,
                'accepted_by_email' => $clientEmail,
                'accepted_by_name' => $team->owner->name ?? $team->name,
            ]);

            // Generate autologin token for the team owner (30 days validity)
            $autologinToken = null;
            if ($team->owner)
            {
                $autologinToken = TokenHelper::generateSignedToken($team->owner, 'sla_acceptance_autologin', 720); // 30 days
            }

            // Generate acceptance URL with autologin if available
            $acceptanceUrl = route('sla.accept', ['token' => $token]);
            if ($autologinToken)
            {
                $acceptanceUrl = route('sla.accept', [
                    'token' => $token,
                    'autologin' => $autologinToken,
                ]);
            }

            // Configure mail for the team
            $this->configureMailForTeam($team);

            // Send email
            Mail::to($clientEmail)->send(new SLAAcceptanceMail($sla, $product, $acceptanceUrl));

            Log::info('SLA acceptance email sent', [
                'sla_id' => $sla->id,
                'product_id' => $product->id,
                'team_id' => $teamId,
                'client_email' => $clientEmail,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email de aceptación de SLA enviado exitosamente',
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error sending SLA acceptance email', [
                'sla_id' => $sla->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el email: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show SLA acceptance page
     */
    public function showAcceptance(Request $request, string $token)
    {
        // Check if autologin token is provided
        $autologinToken = $request->input('autologin');
        if ($autologinToken)
        {
            try
            {
                // First check if token was revoked
                $payload = TokenHelper::getTokenPayload($autologinToken);
                if ($payload)
                {
                    $userId = $payload['user_id'] ?? null;
                    $purpose = $payload['purpose'] ?? 'autologin';
                    if ($userId)
                    {
                        $revocationKey = "user_token_revocation_{$userId}_{$purpose}";
                        $revocationTimestamp = \Illuminate\Support\Facades\Cache::get($revocationKey);
                        if ($revocationTimestamp && isset($payload['iat']) && $payload['iat'] < $revocationTimestamp)
                        {
                            Log::warning('Autologin token was revoked for SLA acceptance', [
                                'user_id' => $userId,
                                'purpose' => $purpose,
                            ]);
                        } else
                        {
                            // Validate and login
                            $user = TokenHelper::validateSignedToken($autologinToken);
                            if ($user)
                            {
                                // Log the user in with remember me
                                auth()->login($user, true);
                                
                                // Switch to the user's current team if available
                                if ($user->currentTeam)
                                {
                                    $user->switchTeam($user->currentTeam);
                                }
                                
                                // Regenerate session to prevent fixation attacks
                                $request->session()->regenerate();
                                
                                Log::info('User autologged in for SLA acceptance', [
                                    'user_id' => $user->id,
                                    'sla_token' => substr($token, 0, 10).'...',
                                ]);
                            }
                        }
                    }
                }
            } catch (\Exception $e)
            {
                Log::warning('Autologin token validation failed for SLA acceptance', [
                    'error' => $e->getMessage(),
                    'token' => substr($autologinToken, 0, 20).'...',
                ]);
                // Continue without autologin if token is invalid
            }
        }

        $acceptance = SLAAcceptance::where('token', $token)
            ->whereNull('accepted_at')
            ->with(['sla.subscriptionProduct', 'subscription.team.owner'])
            ->firstOrFail();

        $sla = $acceptance->sla;
        $product = $sla->subscriptionProduct;
        
        // Get team owner name for auto-filling the name field
        $ownerName = null;
        
        // Try to get from subscription relationship first
        if ($acceptance->subscription && $acceptance->subscription->team && $acceptance->subscription->team->owner)
        {
            $ownerName = $acceptance->subscription->team->owner->name;
        }
        // If not available, try to get from email (find team by owner email)
        else if ($acceptance->accepted_by_email)
        {
            $user = \App\Models\User::where('email', $acceptance->accepted_by_email)->first();
            if ($user)
            {
                $ownerName = $user->name;
            }
            // If user not found, try to find team by email and get owner
            else
            {
                $team = \App\Models\Team::whereHas('owner', function ($q) use ($acceptance)
                {
                    $q->where('email', $acceptance->accepted_by_email);
                })->with('owner')->first();
                
                if ($team && $team->owner)
                {
                    $ownerName = $team->owner->name;
                }
            }
        }

        return view('sla.acceptance', compact('acceptance', 'sla', 'product', 'ownerName'));
    }

    /**
     * Process SLA acceptance
     */
    public function accept(Request $request, string $token)
    {
        $request->validate([
            'accepted_by_name' => 'nullable|string|max:255',
        ]);

        $acceptance = SLAAcceptance::where('token', $token)
            ->whereNull('accepted_at')
            ->with(['sla.subscriptionProduct'])
            ->firstOrFail();

        try
        {
            // Update acceptance record
            $acceptance->update([
                'accepted_at' => now(),
                'accepted_by_name' => $request->input('accepted_by_name', $acceptance->accepted_by_name),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Find subscription for teams with this product
            $product = $acceptance->sla->subscriptionProduct;

            if ($product && $product->stripe_price)
            {
                // Find active subscription with this product's price
                $subscription = Subscription::where('stripe_price', $product->stripe_price)
                    ->where('stripe_status', 'active')
                    ->latest()
                    ->first();

                if ($subscription)
                {
                    // Link acceptance to subscription
                    $acceptance->update(['subscription_id' => $subscription->id]);

                    // Update Stripe subscription metadata
                    $this->updateStripeMetadata($subscription, $acceptance);
                }
            }

            Log::info('SLA accepted successfully', [
                'acceptance_id' => $acceptance->id,
                'sla_id' => $acceptance->sla_id,
                'subscription_id' => $acceptance->subscription_id,
            ]);

            return view('sla.accepted', [
                'sla' => $acceptance->sla,
                'product' => $product,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error accepting SLA', [
                'acceptance_id' => $acceptance->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Error al procesar la aceptación: '.$e->getMessage());
        }
    }

    /**
     * Update Stripe subscription metadata with SLA acceptance
     */
    private function updateStripeMetadata(Subscription $subscription, SLAAcceptance $acceptance): void
    {
        try
        {
            $stripeService = app(StripeSubscriptionService::class);

            // Get current metadata
            $currentMetadata = [];
            if ($subscription->data && is_array($subscription->data))
            {
                $currentMetadata = $subscription->data;
            }

            // Add SLA acceptance metadata
            // Stripe metadata only accepts string values, so we store JSON strings
            $slaKey = 'sla_acceptance_'.$acceptance->sla_id;
            $acceptanceData = [
                'accepted_at' => $acceptance->accepted_at->toIso8601String(),
                'accepted_by_email' => $acceptance->accepted_by_email,
                'accepted_by_name' => $acceptance->accepted_by_name ?? '',
                'sla_title' => $acceptance->sla->title,
                'sla_version' => $acceptance->sla->version,
                'product_id' => $acceptance->sla->subscription_product_id,
            ];

            // Store as JSON string in Stripe metadata (Stripe only accepts string values)
            $stripeMetadata = [];
            foreach ($currentMetadata as $key => $value)
            {
                // Convert arrays to JSON strings for Stripe
                if (is_array($value))
                {
                    $stripeMetadata[$key] = json_encode($value);
                } else
                {
                    $stripeMetadata[$key] = (string) $value;
                }
            }
            $stripeMetadata[$slaKey] = json_encode($acceptanceData);
            $stripeMetadata['sla_acceptance_date_'.$acceptance->sla_id] = $acceptance->accepted_at->toIso8601String();

            // Update in Stripe
            $stripeService->updateMetadata($subscription->stripe_id, $stripeMetadata);

            // Store full data structure locally
            $currentMetadata[$slaKey] = $acceptanceData;

            // Update local subscription data
            $subscription->update(['data' => $currentMetadata]);

            Log::info('Stripe subscription metadata updated with SLA acceptance', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'sla_id' => $acceptance->sla_id,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error updating Stripe metadata for SLA acceptance', [
                'subscription_id' => $subscription->id,
                'acceptance_id' => $acceptance->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw - log the error but continue
        }
    }
}
