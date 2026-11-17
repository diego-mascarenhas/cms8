<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteDomainCheckoutRequest;
use App\Http\Requests\StartDomainCheckoutRequest;
use App\Services\Domains\PublicDomainCheckout;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DomainCheckoutController extends Controller
{
    public function start(StartDomainCheckoutRequest $request, PublicDomainCheckout $checkout): JsonResponse
    {
        $team = $request->user()?->currentTeam;

        if ($team === null)
        {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado o sin equipo'], 401);
        }

        try
        {
            $started = $checkout->start(
                $team,
                [
                    'name' => (string) $request->validated('name'),
                    'email' => (string) $request->validated('email'),
                    'phone' => (string) $request->validated('phone'),
                    'country' => (string) $request->validated('country'),
                    'tax_id' => (string) $request->validated('tax_id'),
                    'business_name' => $request->validated('business_name'),
                ],
                (string) $request->validated('domain'),
                (string) $request->validated('success_url'),
                (string) $request->validated('cancel_url'),
            );
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $started,
        ]);
    }

    public function complete(CompleteDomainCheckoutRequest $request, PublicDomainCheckout $checkout): JsonResponse
    {
        $team = $request->user()?->currentTeam;

        if ($team === null)
        {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado o sin equipo'], 401);
        }

        try
        {
            $done = $checkout->complete($team, (string) $request->validated('session_id'));
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $done,
        ]);
    }
}
