<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendAffiliateInvitationRequest;
use App\Services\AffiliateProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    use ChecksTeamModule;

    public function __construct(
        private readonly AffiliateProgramService $affiliates,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'affiliates'))
        {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => $this->affiliates->dashboard($team),
        ]);
    }

    public function setupStripe(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'affiliates'))
        {
            return $denied;
        }

        if (! $team->canUseAffiliateProgram())
        {
            return response()->json([
                'success' => false,
                'message' => __('Los equipos referidos no pueden usar el programa de afiliados.'),
            ], 403);
        }

        if ($this->affiliates->ensureStripeCustomer($team))
        {
            $team->refresh();

            return response()->json([
                'success' => true,
                'message' => __('Código de referido activado correctamente.'),
                'data' => [
                    'referral_code' => $team->stripe_id,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('No pudimos activar tu código de referido en Stripe. Revisá tus datos de facturación e intentalo de nuevo.'),
        ], 422);
    }

    public function invite(SendAffiliateInvitationRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'affiliates'))
        {
            return $denied;
        }

        try
        {
            $result = $this->affiliates->sendInvitation(
                $request->user(),
                $team,
                $request->validated(),
            );
        } catch (\Illuminate\Validation\ValidationException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? __('No se pudo enviar la invitación.'),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Invitación enviada correctamente.'),
            'data' => $result['invitation'],
        ], 201);
    }
}
