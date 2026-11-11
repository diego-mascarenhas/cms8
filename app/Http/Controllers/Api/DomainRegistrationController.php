<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckDomainRequest;
use App\Services\Domains\PorkbunRegistrar;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DomainRegistrationController extends Controller
{
    public function check(CheckDomainRequest $request, PorkbunRegistrar $porkbun): JsonResponse
    {
        $team = $request->user()?->currentTeam;

        if ($team === null)
        {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado o sin equipo'], 401);
        }

        try
        {
            $quote = $porkbun->check($team, (string) $request->validated('domain'));
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $quote,
        ]);
    }

    public function register(CheckDomainRequest $request, PorkbunRegistrar $porkbun): JsonResponse
    {
        $team = $request->user()?->currentTeam;

        if ($team === null)
        {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado o sin equipo'], 401);
        }

        try
        {
            $registered = $porkbun->register($team, (string) $request->validated('domain'));
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $registered['message'],
            'data' => $registered,
        ]);
    }
}
