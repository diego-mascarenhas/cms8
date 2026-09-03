<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelAssistantSubscriptionRequest;
use App\Http\Requests\Api\CompleteAssistantCheckoutRequest;
use App\Http\Requests\Api\CreateAssistantCheckoutRequest;
use App\Http\Requests\Api\CreateAssistantPaymentMethodRequest;
use App\Http\Requests\Api\RecordMailerUsageRequest;
use App\Http\Requests\Api\ResumeAssistantSubscriptionRequest;
use App\Models\MailerUsageLog;
use App\Services\Billing\AssistantSubscriptionService;
use App\Support\HumanoPricingCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantSubscriptionController extends Controller
{
    public function __construct(
        private AssistantSubscriptionService $subscriptions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->subscriptions->summary($team, $this->requestedCatalog($request)),
        ]);
    }

    public function checkout(CreateAssistantCheckoutRequest $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $result = $this->subscriptions->createCheckout(
            $team,
            (string) $request->validated('interval'),
            (string) $request->validated('success_url'),
            (string) $request->validated('cancel_url'),
            (string) ($request->validated('plan') ?? 'assistant'),
            (int) $request->user()->id,
            $request->validated('pack'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? null,
                'message' => $result['message'] ?? __('No se pudo crear el checkout.'),
            ], 422);
        }

        return response()->json(array_filter([
            'success' => true,
            'url' => $result['url'] ?? null,
            'data' => $result['data'] ?? null,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function complete(CompleteAssistantCheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;
        $result = $this->subscriptions->completeCheckout(
            $team,
            (string) $request->validated('session_id'),
            (int) $user->id,
            (string) ($request->validated('catalog') ?? 'assistant'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('No se pudo completar el checkout.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    public function cancel(CancelAssistantSubscriptionRequest $request): JsonResponse
    {
        $comment = $request->validated('comment');
        $result = $this->subscriptions->cancel(
            $request->user()->currentTeam,
            (string) $request->validated('reason'),
            is_string($comment) ? $comment : null,
            (string) ($request->validated('catalog') ?? 'assistant'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('No se pudo cancelar la suscripción.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    public function resume(ResumeAssistantSubscriptionRequest $request): JsonResponse
    {
        $result = $this->subscriptions->resume(
            $request->user()->currentTeam,
            (string) ($request->validated('catalog') ?? 'assistant'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('No se pudo reanudar la suscripción.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    public function recordMailerUsage(RecordMailerUsageRequest $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $count = (int) ($request->validated('count') ?? 1);
        $source = trim((string) ($request->validated('source') ?? 'app')) ?: 'app';
        $sentAt = $request->validated('sent_at');

        $log = MailerUsageLog::query()->create([
            'team_id' => $team->id,
            'source' => $source,
            'count' => $count,
            'sent_at' => $sentAt ?: now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $log->id,
                'count' => $log->count,
                'source' => $log->source,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function paymentMethod(CreateAssistantPaymentMethodRequest $request): JsonResponse
    {
        $result = $this->subscriptions->createPaymentMethodUpdate(
            $request->user()->currentTeam,
            (string) $request->validated('success_url'),
            (string) $request->validated('cancel_url'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? null,
                'message' => $result['message'] ?? __('No se pudo abrir el cambio de medio de pago.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'url' => $result['url'],
        ]);
    }

    private function requestedCatalog(Request $request): string
    {
        return HumanoPricingCatalog::normalize((string) $request->input('catalog', 'assistant'))
            ?? HumanoPricingCatalog::ASSISTANT;
    }
}
