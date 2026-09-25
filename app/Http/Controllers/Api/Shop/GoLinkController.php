<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Services\Go\GoLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoLinkController extends Controller
{
    use ResolvesShopTeam;

    public function __construct(private GoLinkService $goLinks) {}

    public function ensureCatalogQr(Request $request): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $link = $this->goLinks->ensureShopCatalogQr($team);
        if (! $link)
        {
            return response()->json([
                'success' => false,
                'message' => __('Configurá el nombre del negocio para generar el link de la tienda.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $link->code,
                'url' => $link->publicUrl(),
                'target_url' => $link->target_url,
                'hits_count' => $link->hits_count,
            ],
        ]);
    }
}
