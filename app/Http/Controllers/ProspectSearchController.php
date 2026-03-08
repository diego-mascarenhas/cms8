<?php

namespace App\Http\Controllers;

use App\Mail\ProspectResultsAccessMail;
use App\Models\Contact;
use App\Models\Team;
use App\Services\ApolloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProspectSearchController extends Controller
{
    /**
     * Prospect Search landing (public).
     */
    public function index(): View
    {
        return view('prospect-search-demo');
    }

    /**
     * Search people via Apollo API – public, returns max 10 results.
     */
    public function searchPeople(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_titles' => 'nullable|array',
            'person_titles.*' => 'string|max:255',
            'person_locations' => 'nullable|array',
            'person_locations.*' => 'string|max:255',
            'person_seniorities' => 'nullable|array',
            'person_seniorities.*' => 'string|max:50',
            'organization_locations' => 'nullable|array',
            'organization_locations.*' => 'string|max:255',
            'q_organization_domains_list' => 'nullable|array',
            'q_organization_domains_list.*' => 'string|max:255',
            'q_keywords' => 'nullable|string|max:500',
        ]);

        $filters = array_filter([
            'person_titles' => $validated['person_titles'] ?? null,
            'person_locations' => $validated['person_locations'] ?? null,
            'person_seniorities' => $validated['person_seniorities'] ?? null,
            'organization_locations' => $validated['organization_locations'] ?? null,
            'q_organization_domains_list' => $validated['q_organization_domains_list'] ?? null,
            'q_keywords' => $validated['q_keywords'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        try
        {
            $service = new ApolloService;
            $result = $service->searchPeople($filters, 1, 10);

            $people = array_slice($result['people'], 0, 10);
            $result['people'] = $people;
            $result['per_page'] = 10;

            return response()->json($result);
        } catch (\RuntimeException $e)
        {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return response()->json(
                ['message' => $e->getMessage()],
                $status,
            );
        }
    }

    /**
     * Store lead, send email with access link, and return hash so frontend can redirect to same page.
     */
    public function storeLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email:rfc|max:255',
            'person_titles' => 'nullable|array',
            'person_titles.*' => 'string|max:255',
            'person_locations' => 'nullable|array',
            'person_locations.*' => 'string|max:255',
            'person_seniorities' => 'nullable|array',
            'person_seniorities.*' => 'string|max:50',
            'organization_locations' => 'nullable|array',
            'organization_locations.*' => 'string|max:255',
            'q_organization_domains_list' => 'nullable|array',
            'q_organization_domains_list.*' => 'string|max:255',
            'q_keywords' => 'nullable|string|max:500',
        ]);

        $teamId = config('services.prospect_search.team_id');
        if (empty($teamId))
        {
            return response()->json([
                'message' => __('Prospect Search is not configured.'),
                'success' => false,
            ], 503);
        }

        $team = Team::find($teamId);
        if (! $team)
        {
            return response()->json([
                'message' => __('Invalid configuration.'),
                'success' => false,
            ], 503);
        }

        Contact::withoutGlobalScopes()->firstOrCreate(
            [
                'email' => $validated['email'],
                'team_id' => $team->id,
            ],
            [
                'name' => Str::before($validated['email'], '@') ?: $validated['email'],
                'team_id' => $team->id,
                'status_id' => 3,
                'creator_id' => $team->user_id,
            ],
        );

        $filters = array_filter([
            'person_titles' => $validated['person_titles'] ?? null,
            'person_locations' => $validated['person_locations'] ?? null,
            'person_seniorities' => $validated['person_seniorities'] ?? null,
            'organization_locations' => $validated['organization_locations'] ?? null,
            'q_organization_domains_list' => $validated['q_organization_domains_list'] ?? null,
            'q_keywords' => $validated['q_keywords'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        $hash = Str::random(64);
        $code = strtoupper(Str::random(6));
        $payload = [
            'email' => $validated['email'],
            'filters' => $filters,
            'hash' => $hash,
            'code' => $code,
        ];
        $ttl = now()->addHours(24);
        Cache::put('prospect_access:'.$hash, $payload, $ttl);
        Cache::put('prospect_access_code:'.$code, $payload, $ttl);

        $baseUrl = config('services.prospect_search.access_base_url');
        $accessUrl = ! empty($baseUrl) ? rtrim($baseUrl, '/').'?access='.$hash : '';

        Mail::to($validated['email'])->send(new ProspectResultsAccessMail($code, $accessUrl));

        return response()->json([
            'message' => __('Revisa tu correo. Te hemos enviado un código para ver los resultados.'),
            'success' => true,
        ], 201);
    }

    /**
     * Validate code or hash and return results (one-time use).
     */
    public function access(Request $request): JsonResponse
    {
        $code = $request->query('code');
        $hash = $request->query('hash') ?? $request->query('access');

        if (! empty($code))
        {
            $key = 'prospect_access_code:'.$code;
        } elseif (! empty($hash))
        {
            $key = 'prospect_access:'.$hash;
        } else
        {
            return response()->json(['message' => __('Código o enlace inválido.')], 404);
        }

        $data = Cache::get($key);
        if (! $data || ! is_array($data))
        {
            return response()->json(['message' => __('Código o enlace inválido o expirado.')], 404);
        }

        Cache::forget($key);
        if (! empty($data['hash']))
        {
            Cache::forget('prospect_access:'.$data['hash']);
        }
        if (! empty($data['code']))
        {
            Cache::forget('prospect_access_code:'.$data['code']);
        }

        $filters = $data['filters'] ?? [];
        $email = $data['email'] ?? '';

        if (empty($filters))
        {
            return response()->json(['message' => __('No hay búsqueda asociada.')], 404);
        }

        try
        {
            $service = new ApolloService;
            $result = $service->searchPeople($filters, 1, 10);
            $people = array_slice($result['people'], 0, 10);

            return response()->json([
                'people' => $people,
                'total_entries' => $result['total_entries'] ?? count($people),
                'email' => $email,
            ]);
        } catch (\RuntimeException $e)
        {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502,
            );
        }
    }

    /**
     * Create Stripe Checkout URL for CSV export. Frontend redirects to this URL (checkout from frontend).
     */
    public function createExportCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email:rfc|max:255',
        ]);

        $priceId = config('services.prospect_search.export_price_id');
        if (empty($priceId) || str_contains($priceId, 'REPLACE'))
        {
            return response()->json([
                'message' => __('Export checkout is not configured.'),
                'success' => false,
            ], 503);
        }

        $successUrl = config('services.prospect_search.checkout_success_url');
        $cancelUrl = config('services.prospect_search.checkout_cancel_url');
        if (empty($successUrl) || empty($cancelUrl))
        {
            return response()->json([
                'message' => __('Checkout URLs are not configured.'),
                'success' => false,
            ], 503);
        }

        try
        {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            $session = \Stripe\Checkout\Session::create([
                'mode' => 'payment',
                'locale' => 'es',
                'customer_email' => $validated['email'],
                'client_reference_id' => $validated['email'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => rtrim($successUrl, '/').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'source' => 'prospect_search_export',
                    'lead_email' => $validated['email'],
                ],
            ]);

            return response()->json([
                'success' => true,
                'url' => $session->url,
            ]);
        } catch (\Throwable $e)
        {
            \Log::error('Prospect export checkout error: '.$e->getMessage());

            return response()->json([
                'message' => __('Error al crear la sesión de pago.'),
                'success' => false,
            ], 502);
        }
    }
}
