<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionProduct;
use App\Models\Team;
use App\Services\Billing\TeamBillingDataService;
use App\Services\StripeAccountResolver;
use App\Services\TaxIdentifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function __construct(
        private TeamBillingDataService $billingData,
        private TaxIdentifierService $taxIdentifierService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->billingData->buildPayload($team),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessBilling())
        {
            return response()->json([
                'success' => false,
                'message' => __('No tienes acceso a facturación.'),
            ], 403);
        }

        $team = $user->currentTeam;

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $subscriptions = $team->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->orderByDesc('created_at')
            ->get();

        $plans = $subscriptions
            ->map(function (Subscription $subscription): array
            {
                return [
                    'id' => $subscription->id,
                    'type' => (string) $subscription->type,
                    'status' => (string) $subscription->stripe_status,
                    'price' => $subscription->stripe_price,
                    'ends_at' => $subscription->ends_at?->toDateString(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $this->catalogProducts(),
                'plans' => $plans,
                'invoices' => $this->stripeInvoices($team),
            ],
        ]);
    }

    /**
     * @return list<array{id: int, name: string, description: string|null, category: string|null, category_label: string, plan: string|null, type: string|null, price: string, interval: string|null, active: bool}>
     */
    private function catalogProducts(): array
    {
        return SubscriptionProduct::query()
            ->orderBy('name')
            ->get()
            ->map(function (SubscriptionProduct $product): array
            {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category' => $product->category,
                    'category_label' => SubscriptionProduct::getCategoryLabel($product->category),
                    'plan' => $product->plan,
                    'type' => $product->type,
                    'price' => $product->getFormattedPrice(),
                    'interval' => $product->getBillingFrequency(),
                    'active' => (bool) $product->active,
                ];
            })
            ->values()
            ->all();
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $validated = $request->validate([
            'individual_name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:2'],
            'phone' => ['required', 'string', 'max:50'],
            'tax_id' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($request)
                {
                    $taxId = $this->taxIdentifierService->normalize($value);
                    if ($taxId === '' || ! $this->taxIdentifierService->isValidForCountry($request->country, $taxId))
                    {
                        $fail(__('El formato de la Identificación Fiscal no es válido para el país seleccionado.'));
                    }
                },
            ],
        ]);

        $result = $this->billingData->updateBilling($team, $validated);

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        $response = [
            'success' => true,
            'message' => $result['message'],
            'data' => $this->billingData->buildPayload($team->fresh()),
        ];

        if (! empty($result['warning']))
        {
            $response['warning'] = $result['warning'];
        }

        $user->forceFill([
            'phone' => preg_replace('/\D+/', '', $validated['phone']) ?: null,
        ])->save();

        return response()->json($response);
    }

    /**
     * @return list<array{id: string, number: string|null, status: string, amount_paid: int, amount_due: int, currency: string, created_at: string|null, hosted_invoice_url: string|null, invoice_pdf: string|null}>
     */
    private function stripeInvoices(Team $team): array
    {
        $customerId = trim((string) $team->stripe_id);
        $secret = StripeAccountResolver::secretForCategory('mailer');

        if ($customerId === '' || $secret === '')
        {
            return [];
        }

        try
        {
            \Stripe\Stripe::setApiKey($secret);
            $customer = \Stripe\Customer::retrieve($customerId);
            $customerTeamId = $customer->metadata->team_id ?? null;

            if ($customerTeamId && (int) $customerTeamId !== (int) $team->id)
            {
                return [];
            }

            $invoices = \Stripe\Invoice::all([
                'customer' => $customerId,
                'limit' => 20,
            ]);

            return collect($invoices->data)->map(function ($invoice): array
            {
                return [
                    'id' => (string) $invoice->id,
                    'number' => $invoice->number ? (string) $invoice->number : null,
                    'status' => (string) $invoice->status,
                    'amount_paid' => (int) $invoice->amount_paid,
                    'amount_due' => (int) $invoice->amount_due,
                    'currency' => strtoupper((string) $invoice->currency),
                    'created_at' => isset($invoice->created) ? date('c', (int) $invoice->created) : null,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url ? (string) $invoice->hosted_invoice_url : null,
                    'invoice_pdf' => $invoice->invoice_pdf ? (string) $invoice->invoice_pdf : null,
                ];
            })->values()->all();
        } catch (\Exception $exception)
        {
            Log::warning('Could not load billing invoices', [
                'team_id' => $team->id,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
