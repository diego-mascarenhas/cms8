<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRM clients (enterprises) for mobile — gated by the "clients" team module.
 */
class ClientController extends Controller
{
    use ChecksTeamModule;

    public function __construct(
        private EnterpriseController $enterprises,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'clients'))
        {
            return $denied;
        }

        return $this->enterprises->index($request);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'clients'))
        {
            return $denied;
        }

        return $this->enterprises->show($request, $id);
    }
}
