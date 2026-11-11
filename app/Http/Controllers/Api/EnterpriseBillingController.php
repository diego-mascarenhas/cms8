<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use App\Services\Stripe\CustomerPaymentMethodReader;
use App\Services\Stripe\EnterpriseCardSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EnterpriseBillingController extends Controller
{
    public function show(Request $request, string $id, CustomerPaymentMethodReader $cards): JsonResponse
    {
        $enterprise = $this->enterprise($request, $id);

        if ($enterprise instanceof JsonResponse)
        {
            return $enterprise;
        }

        $customerId = $enterprise->getStripeCustomerId();
        $registered = is_string($customerId) && str_starts_with($customerId, 'cus_');
        $saved = $registered ? $cards->cardsForCustomer($enterprise->team, $customerId) : [];

        return response()->json([
            'success' => true,
            'registered' => $registered,
            'card' => $this->defaultCard($saved),
            'cards' => $saved,
        ]);
    }

    public function setup(Request $request, string $id, EnterpriseCardSetup $setup): JsonResponse
    {
        $enterprise = $this->enterprise($request, $id);

        if ($enterprise instanceof JsonResponse)
        {
            return $enterprise;
        }

        try
        {
            $prepared = $setup->begin($enterprise);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'client_secret' => $prepared['client_secret'],
            'publishable_key' => $prepared['publishable_key'],
        ]);
    }

    public function confirm(Request $request, string $id, EnterpriseCardSetup $setup): JsonResponse
    {
        $enterprise = $this->enterprise($request, $id);

        if ($enterprise instanceof JsonResponse)
        {
            return $enterprise;
        }

        $setupIntentId = trim((string) $request->input('setup_intent', ''));

        if (! str_starts_with($setupIntentId, 'seti_'))
        {
            return response()->json([
                'success' => false,
                'message' => 'La confirmación de la tarjeta no es válida.',
            ], 422);
        }

        try
        {
            $card = $setup->complete($enterprise, $setupIntentId);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($card === null)
        {
            return response()->json([
                'success' => false,
                'message' => 'La tarjeta no quedó guardada.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'registered' => true,
            'card' => $card,
        ]);
    }

    public function choose(Request $request, string $id, EnterpriseCardSetup $setup, CustomerPaymentMethodReader $cards): JsonResponse
    {
        $enterprise = $this->enterprise($request, $id);

        if ($enterprise instanceof JsonResponse)
        {
            return $enterprise;
        }

        try
        {
            $setup->useDefault($enterprise, (string) $request->input('payment_method', ''));
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $customerId = $enterprise->getStripeCustomerId();
        $saved = is_string($customerId) ? $cards->cardsForCustomer($enterprise->team, $customerId) : [];

        return response()->json([
            'success' => true,
            'card' => $this->defaultCard($saved),
            'cards' => $saved,
        ]);
    }

    /**
     * @param  list<array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, default: bool}>  $cards
     * @return array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, default: bool}|null
     */
    private function defaultCard(array $cards): ?array
    {
        foreach ($cards as $card)
        {
            if ($card['default'])
            {
                return $card;
            }
        }

        return $cards[0] ?? null;
    }

    private function enterprise(Request $request, string $id): Enterprise|JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->currentTeam)
        {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado o sin equipo',
            ], 401);
        }

        $enterprise = Enterprise::query()->whereKey($id)->first();

        if (! $enterprise || ! $user->can('view', $enterprise))
        {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada',
            ], 404);
        }

        return $enterprise;
    }
}
