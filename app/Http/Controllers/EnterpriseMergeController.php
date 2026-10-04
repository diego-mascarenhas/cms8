<?php

namespace App\Http\Controllers;

use App\Exceptions\EnterpriseMergeException;
use App\Http\Requests\MergeEnterpriseRequest;
use App\Models\Enterprise;
use App\Services\EnterpriseMergeService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnterpriseMergeController extends Controller
{
    public function candidates(Request $request, string $id): JsonResponse
    {
        $enterprise = $this->enterpriseForUpdate($id);
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2)
        {
            return response()->json(['enterprises' => []]);
        }

        $query = Enterprise::query()
            ->where('team_id', $enterprise->team_id)
            ->where('id', '!=', $enterprise->id);
        SearchNormalizer::applyEnterpriseNavbarConditions($query, $term);

        $enterprises = $query
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'code', 'email']);

        return response()->json([
            'enterprises' => $enterprises->map(function (Enterprise $row): array
            {
                $stripe = $row->getStripeCustomerId();

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'subtitle' => $stripe
                        ? 'Código: '.$stripe
                        : ($row->email ? (string) $row->email : 'Sin código de Stripe'),
                    'stripe' => $stripe !== null,
                ];
            })->values(),
        ]);
    }

    public function preview(Request $request, string $id, EnterpriseMergeService $merge): JsonResponse
    {
        $current = $this->enterpriseForUpdate($id);
        $other = $this->otherEnterprise($current, (int) $request->query('enterprise_id'));

        return response()->json($merge->preview($current, $other));
    }

    public function store(MergeEnterpriseRequest $request, string $id, EnterpriseMergeService $merge): JsonResponse
    {
        $current = $this->enterpriseForUpdate($id);
        $other = $this->otherEnterprise($current, (int) $request->validated('enterprise_id'));

        try
        {
            $survivor = $merge->merge($current, $other);
        } catch (EnterpriseMergeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Empresas fusionadas. Se conservó '.$survivor->name.'.',
            'redirect' => route('empresas.show', $survivor->id),
        ]);
    }

    private function enterpriseForUpdate(string $id): Enterprise
    {
        $enterprise = Enterprise::query()
            ->where('id', $id)
            ->where('team_id', auth()->user()->current_team_id)
            ->firstOrFail();

        $this->authorize('update', $enterprise);

        return $enterprise;
    }

    private function otherEnterprise(Enterprise $current, int $otherId): Enterprise
    {
        $other = Enterprise::query()
            ->where('id', $otherId)
            ->where('team_id', $current->team_id)
            ->firstOrFail();

        $this->authorize('update', $other);

        return $other;
    }
}
