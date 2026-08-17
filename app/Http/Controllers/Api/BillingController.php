<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\TeamBillingDataService;
use App\Services\TaxIdentifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'phone' => $validated['phone'],
        ])->save();

        return response()->json($response);
    }
}
