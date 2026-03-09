<?php

namespace App\Http\Controllers;

use App\Mail\ProspectResultsAccessMail;
use App\Models\Contact;
use App\Models\SubscriptionProduct;
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
            'send_email' => 'nullable|boolean',
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

        $sendEmail = $validated['send_email'] ?? true;
        if ($sendEmail)
        {
            Mail::to($validated['email'])->send(new ProspectResultsAccessMail($code, $accessUrl));
        }

        $payload = [
            'message' => __('Revisa tu correo. Te hemos enviado un código para ver los resultados.'),
            'success' => true,
        ];
        if (! $sendEmail)
        {
            $payload['code'] = $code;
            $payload['access_url'] = $accessUrl;
            $payload['hash'] = $hash;
        }

        return response()->json($payload, 201);
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
     * Return Stripe checkout config for the Prospection frontend (publishable key, price ID, return URL base).
     * Frontend can use this instead of env vars to unify configuration in the backend.
     */
    public function checkoutConfig(Request $request): JsonResponse
    {
        $config = config('services.prospect_search', []);
        $priceId = SubscriptionProduct::getProspectionPriceId() ?? $config['export_price_id'] ?? null;
        $appUrl = isset($config['access_base_url']) ? rtrim($config['access_base_url'], '/') : null;

        return response()->json([
            'stripePublishableKey' => config('cashier.key') ?: null,
            'priceId' => $priceId,
            'returnUrlBase' => $appUrl,
        ]);
    }

    /**
     * Create Stripe Checkout URL for CSV export. Frontend sends email + filters; after payment user can download CSV.
     * price_id is optional when backend has export_price_id configured (use checkout-config to get it).
     */
    public function createExportCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email:rfc|max:255',
            'return_url' => 'required|url|max:500',
            'price_id' => 'nullable|string|max:255|starts_with:price_',
            'quantity' => 'nullable|integer|min:1',
            'contact_count' => 'nullable|integer|min:1|max:100000',
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

        $priceId = $validated['price_id'] ?? SubscriptionProduct::getProspectionPriceId() ?? config('services.prospect_search.export_price_id');
        if (empty($priceId) || ! str_starts_with($priceId, 'price_'))
        {
            return response()->json([
                'message' => __('El producto de exportación no está configurado.'),
                'success' => false,
            ], 502);
        }

        $returnUrl = rtrim($validated['return_url'], '/').'?session_id={CHECKOUT_SESSION_ID}';
        $quantity = (int) ($validated['quantity'] ?? 1);
        $quantity = max(1, $quantity);
        $contactCount = isset($validated['contact_count']) ? (int) $validated['contact_count'] : null;
        if ($contactCount !== null)
        {
            $contactCount = max(1, min(100000, $contactCount));
        }

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
            \Stripe\Stripe::setApiKey(\App\Services\StripeAccountResolver::secretForCategory('prospecting'));

            $session = \Stripe\Checkout\Session::create([
                'ui_mode' => 'embedded',
                'mode' => 'payment',
                'locale' => 'es',
                'customer_email' => $validated['email'],
                'client_reference_id' => $validated['email'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => $quantity,
                ]],
                'return_url' => $returnUrl,
                'metadata' => [
                    'source' => 'prospect_search_export',
                    'lead_email' => $validated['email'],
                ],
            ]);

            Cache::put('prospect_export_session:'.$session->id, [
                'email' => $validated['email'],
                'filters' => $filters,
                'quantity' => $contactCount ?? $quantity,
            ], now()->addHours(24));

            return response()->json([
                'success' => true,
                'clientSecret' => $session->client_secret,
            ]);
        } catch (\Throwable $e)
        {
            \Log::error('Prospect export checkout error: '.$e->getMessage(), [
                'exception' => $e::class,
                'quantity' => $quantity,
            ]);

            $message = __('Error al crear la sesión de pago.');
            if (config('app.debug') && $e->getMessage() !== '') {
                $message .= ' '.$e->getMessage();
            }

            return response()->json([
                'message' => $message,
                'success' => false,
            ], 502);
        }
    }

    /**
     * Download CSV after successful Stripe payment. One-time use per session.
     */
    public function downloadExportCsv(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (empty($sessionId))
        {
            return response()->json(['message' => __('Sesión inválida.')], 400);
        }

        $cacheKey = 'prospect_export_session:'.$sessionId;
        $data = Cache::get($cacheKey);
        if (! $data || ! is_array($data))
        {
            return response()->json(['message' => __('Enlace de descarga inválido o expirado.')], 404);
        }

        try
        {
            \Stripe\Stripe::setApiKey(\App\Services\StripeAccountResolver::secretForCategory('prospecting'));
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            if (! $session || ($session->payment_status ?? '') !== 'paid')
            {
                return response()->json(['message' => __('El pago no se ha completado.')], 403);
            }
        } catch (\Throwable $e)
        {
            \Log::warning('Prospect export CSV: Stripe session check failed: '.$e->getMessage());

            return response()->json(['message' => __('No se pudo verificar el pago.')], 502);
        }

        $filters = $data['filters'] ?? [];
        $quantity = (int) ($data['quantity'] ?? 500);
        $quantity = max(1, min(100000, $quantity));

        if (empty($filters))
        {
            return response()->json(['message' => __('No hay búsqueda asociada.')], 404);
        }

        $filename = 'prospectos-'.date('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        Cache::forget($cacheKey);

        $perPage = 100;

        return response()->streamDownload(function () use ($filters, $quantity, $perPage)
        {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nombre', 'Apellido', 'Título', 'Empresa'], ';');

            $service = new ApolloService;
            $page = 1;
            $collected = 0;

            while ($collected < $quantity)
            {
                try
                {
                    $result = $service->searchPeople($filters, $page, $perPage);
                } catch (\RuntimeException $e)
                {
                    break;
                }

                $people = $result['people'] ?? [];
                if (empty($people))
                {
                    break;
                }

                foreach ($people as $p)
                {
                    if ($collected >= $quantity)
                    {
                        break;
                    }
                    $org = $p['organization'] ?? [];
                    $orgName = is_array($org) ? ($org['name'] ?? '') : '';
                    $lastName = $p['last_name'] ?? $p['last_name_obfuscated'] ?? '';
                    fputcsv($out, [
                        $p['first_name'] ?? '',
                        $lastName,
                        $p['title'] ?? '',
                        $orgName,
                    ], ';');
                    $collected++;
                }

                if (count($people) < $perPage)
                {
                    break;
                }
                $page++;
            }

            fclose($out);
        }, $filename, $headers);
    }
}
