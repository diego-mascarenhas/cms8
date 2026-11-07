<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use App\Models\Service;
use App\Models\ServiceSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado o sin equipo'], 401);
        }

        if (! $user->canAccessBilling())
        {
            return response()->json(['success' => false, 'error' => 'No tienes permisos para ver suscripciones'], 403);
        }

        $subscriptions = ServiceSync::query()
            ->with('enterprise:id,name,code')
            ->where('team_id', $user->currentTeam->id)
            ->orderByDesc('current_period_end')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (ServiceSync $sync) => $this->present($sync));

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $sync = $this->findForUser($request, $id);

        if ($sync instanceof JsonResponse)
        {
            return $sync;
        }

        return response()->json([
            'success' => true,
            'data' => $this->present($sync, true),
        ]);
    }

    public function forEnterprise(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado o sin equipo'], 401);
        }

        $enterprise = Enterprise::query()->whereKey($id)->first();

        if (! $enterprise)
        {
            return response()->json(['success' => false, 'error' => 'Empresa no encontrada'], 404);
        }

        $linkedIds = Service::query()
            ->where('enterprise_id', $enterprise->id)
            ->whereNotNull('subscription_id')
            ->pluck('subscription_id');

        $subscriptions = ServiceSync::query()
            ->where('team_id', $user->currentTeam->id)
            ->where(function ($query) use ($enterprise, $linkedIds)
            {
                $query->whereIn('id', $linkedIds);

                if (filled($enterprise->code))
                {
                    $query->orWhere('customer_id', $enterprise->code);
                }

                if (filled($enterprise->email))
                {
                    $query->orWhere('customer_email', $enterprise->email);
                }
            })
            ->orderByDesc('current_period_end')
            ->get()
            ->map(fn (ServiceSync $sync) => $this->present($sync, true))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    private function findForUser(Request $request, string $id): ServiceSync|JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado o sin equipo'], 401);
        }

        if (! $user->canAccessBilling())
        {
            return response()->json(['success' => false, 'error' => 'No tienes permisos para ver suscripciones'], 403);
        }

        $sync = ServiceSync::query()
            ->with('enterprise:id,name,code')
            ->where('team_id', $user->currentTeam->id)
            ->whereKey($id)
            ->first();

        if (! $sync)
        {
            return response()->json(['success' => false, 'error' => 'Suscripción no encontrada'], 404);
        }

        return $sync;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ServiceSync $sync, bool $withMetadata = false): array
    {
        $payload = [
            'id' => $sync->id,
            'stripe_id' => $sync->stripe_id,
            'name' => $sync->clientFacingName(),
            'status' => $sync->status,
            'plan_name' => $sync->plan_name,
            'interval' => $sync->billing_frequency,
            'quantity' => $sync->quantity,
            'currency' => $sync->price_currency,
            'amount' => $sync->amount_total,
            'customer_name' => $sync->customer_name,
            'customer_email' => $sync->customer_email,
            'enterprise' => $sync->relationLoaded('enterprise') && $sync->enterprise ? [
                'id' => $sync->enterprise->id,
                'name' => $sync->enterprise->name,
            ] : null,
            'current_period_start' => $sync->current_period_start?->toDateString(),
            'current_period_end' => $sync->current_period_end?->toDateString(),
            'cancel_at_period_end' => (bool) $sync->cancel_at_period_end,
            'category' => $sync->clientCategoryLabel(),
        ];

        if ($withMetadata)
        {
            $payload['metadata'] = $this->metadata($sync);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(ServiceSync $sync): array
    {
        $fromData = data_get($sync->data, 'metadata');
        $fromRaw = data_get($sync->raw_payload, 'metadata');
        $meta = is_array($fromData) && $fromData !== [] ? $fromData : (is_array($fromRaw) ? $fromRaw : []);

        foreach (['category', 'domain', 'type'] as $key)
        {
            if (! array_key_exists($key, $meta) && filled(data_get($sync->data, $key)))
            {
                $meta[$key] = data_get($sync->data, $key);
            }
        }

        return $meta;
    }
}
