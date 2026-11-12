<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckDomainRequest;
use App\Http\Requests\RegisterDomainRequest;
use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Service;
use App\Services\Domains\EnterpriseDomainCharge;
use App\Services\Domains\PorkbunRegistrar;
use App\Services\Hosting\HostingFailureNotice;
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

    public function register(
        RegisterDomainRequest $request,
        PorkbunRegistrar $porkbun,
        EnterpriseDomainCharge $charger,
        HostingFailureNotice $failures,
    ): JsonResponse {
        $user = $request->user();
        $team = $user?->currentTeam;

        if ($user === null || $team === null)
        {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado o sin equipo'], 401);
        }

        $enterprise = Enterprise::query()
            ->whereKey((int) $request->validated('enterprise_id'))
            ->where('team_id', $team->id)
            ->first();

        if ($enterprise === null)
        {
            return response()->json([
                'success' => false,
                'message' => 'La empresa no pertenece a este equipo.',
            ], 422);
        }

        try
        {
            $quote = $porkbun->quote($team, (string) $request->validated('domain'));

            if (! $quote['available'] || $quote['price'] === null || $quote['cost'] === null)
            {
                throw new RuntimeException('Ese dominio no está disponible.');
            }

            $amountCents = (int) round($quote['price'] * 100);
            $charge = $charger->subscribe(
                $enterprise,
                $amountCents,
                $quote['currency'],
                'Dominio anual '.$quote['domain'],
                [
                    'enterprise_id' => (string) $enterprise->id,
                    'domain' => $quote['domain'],
                    'wholesale' => (string) $quote['cost'],
                    'markup_percent' => (string) $quote['markup_percent'],
                ],
            );

            try
            {
                $registered = $porkbun->register($team, $quote['domain']);
            } catch (RuntimeException $exception)
            {
                // Manual review: keep the Stripe subscription and alert ops instead of auto-refunding.
                // $charger->refund((string) ($charge['payment_intent_id'] ?? ''), $enterprise);
                $failures->report($exception->getMessage(), [
                    'domain' => $quote['domain'],
                    'enterprise_id' => $enterprise->id,
                    'enterprise' => $enterprise->name,
                    'subscription_id' => $charge['subscription_id'],
                    'payment_intent_id' => $charge['payment_intent_id'],
                    'amount_cents' => $charge['amount_cents'],
                    'currency' => $charge['currency'],
                    'wholesale' => $quote['cost'],
                    'price' => $quote['price'],
                ]);

                throw $exception;
            }

            $service = $this->storeAnnualService(
                $user->id,
                $enterprise->id,
                $charge['service_sync_id'],
                $registered['domain'],
                $registered['cost'],
                $registered['price'],
                $registered['markup_percent'],
                $charge['subscription_id'],
                $charge['payment_intent_id'],
            );

            $registered['charged'] = true;
            $registered['amount_cents'] = $charge['amount_cents'];
            $registered['subscription_id'] = $charge['subscription_id'];
            $registered['payment_intent_id'] = $charge['payment_intent_id'];
            $registered['service_id'] = $service->id;
            $registered['service_sync_id'] = $charge['service_sync_id'];
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

    private function storeAnnualService(
        int $userId,
        int $enterpriseId,
        int $serviceSyncId,
        string $domain,
        ?float $cost,
        ?float $price,
        float $markupPercent,
        string $subscriptionId,
        ?string $paymentIntentId,
    ): Service {
        $categoryId = Category::query()
            ->where('name', 'like', '%Dominio%')
            ->value('id');

        if ($categoryId === null)
        {
            $categoryId = Category::query()->create([
                'name' => 'Dominios',
                'team_id' => Enterprise::query()->whereKey($enterpriseId)->value('team_id'),
                'status' => true,
            ])->id;
        }

        return Service::query()->create([
            'enterprise_id' => $enterpriseId,
            'subscription_id' => $serviceSyncId,
            'category_id' => $categoryId,
            'operation' => 'sell',
            'description' => 'Dominio '.$domain.' (anual)',
            'data' => [
                'domain' => $domain,
                'period' => 'annual',
                'cost' => $cost,
                'price' => $price,
                'currency' => 'USD',
                'markup_percent' => $markupPercent,
                'stripe_subscription' => $subscriptionId,
                'stripe_payment_intent' => $paymentIntentId,
            ],
            'responsible_id' => $userId,
            'status' => 4,
            'frequency' => 12,
            'next_billing' => now()->addYear()->toDateString(),
        ]);
    }
}
