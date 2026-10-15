<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommunicationRequest;
use App\Models\Communication;
use App\Models\Team;
use App\Services\Communications\CommunicationService;
use App\Services\MailBabyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CommunicationController extends Controller
{
    use ChecksTeamModule;

    public function __construct(private CommunicationService $communications) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'channel' => 'nullable|string',
            'status' => 'nullable|string',
            'sent_today' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Communication::query()
            ->where('team_id', $team->id)
            ->with('contact')
            ->latest('id');

        $channel = CommunicationChannel::tryFrom((string) ($validated['channel'] ?? ''));
        if ($channel)
        {
            $query->where('channel', $channel);
        }

        $status = CommunicationStatus::tryFrom((string) ($validated['status'] ?? ''));
        if ($request->boolean('sent_today'))
        {
            $query->where('status', CommunicationStatus::Sent)
                ->whereDate('sent_at', now()->toDateString());
        } elseif ($status)
        {
            $query->where('status', $status);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            $query->where(function ($builder) use ($search)
            {
                $builder->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('recipient_email', 'like', '%'.$search.'%')
                    ->orWhere('recipient_phone', 'like', '%'.$search.'%')
                    ->orWhere('recipient_name', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        $items = $paginator->getCollection()
            ->map(fn (Communication $communication) => $this->communications->format($communication, false))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->communications->stats($team),
        ]);
    }

    public function channels(Request $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->communications->channelStatus($team),
        ]);
    }

    public function store(StoreCommunicationRequest $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $attachments = $request->file('attachments', []);
        if ($attachments instanceof UploadedFile)
        {
            $attachments = [$attachments];
        }

        $attachments = array_values(array_filter(
            is_array($attachments) ? $attachments : [],
            fn ($file) => $file instanceof UploadedFile,
        ));

        $communication = $this->communications->create(
            $team,
            $request->validated(),
            $request->user(),
            $attachments,
        );

        return response()->json([
            'success' => true,
            'message' => __('Communication queued successfully'),
            'data' => $this->communications->format($communication),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $communication = $this->findForTeam($team, $id);
        if (! $communication)
        {
            return response()->json([
                'success' => false,
                'message' => __('Communication not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->communications->format($communication),
        ]);
    }

    public function log(Request $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $communication = $this->findForTeam($team, $id);
        if (! $communication)
        {
            return response()->json([
                'success' => false,
                'message' => __('Communication not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->deliveryLogPayload($communication),
        ]);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $communication = $this->findForTeam($team, $id);
        if (! $communication)
        {
            return response()->json([
                'success' => false,
                'message' => __('Communication not found'),
            ], 404);
        }

        if (! $communication->canResend())
        {
            return response()->json([
                'success' => false,
                'message' => __('Only sent or failed communications can be resent.'),
            ], 422);
        }

        $communication = $this->communications->retry($communication);

        return response()->json([
            'success' => true,
            'message' => __('Communication queued successfully'),
            'data' => $this->communications->format($communication),
        ]);
    }

    public function docsToken(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'api_token' => $this->plainTeamApiToken($team),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryLogPayload(Communication $communication): array
    {
        $metadata = is_array($communication->metadata) ? $communication->metadata : [];
        $mailId = is_string($metadata['provider_message_id'] ?? null) ? $metadata['provider_message_id'] : null;
        $provider = is_string($metadata['email_provider'] ?? null) ? $metadata['email_provider'] : null;
        $record = null;

        if ($communication->channel === CommunicationChannel::Email && config('services.mailbaby.api_key'))
        {
            $sentAt = $communication->sent_at ?? $communication->created_at;
            $record = app(MailBabyService::class)->findDeliveryRecord(
                $mailId,
                $communication->recipient_email,
                $communication->subject,
                $sentAt?->copy()->subDay()->format('Y-m-d'),
            );
        }

        return [
            'found' => (bool) ($record['found'] ?? false),
            'id' => $record['id'] ?? $mailId,
            'delivered' => $record['delivered'] ?? null,
            'code' => $record['code'] ?? null,
            'response' => $record['response'] ?? null,
            'created' => $record['created'] ?? null,
            'user' => $record['user'] ?? null,
            'subject' => $record['subject'] ?? null,
            'from' => $record['from'] ?? null,
            'to' => $record['to'] ?? $communication->recipient_email,
            'email_provider' => $provider,
            'error_message' => $communication->error_message,
        ];
    }

    private function findForTeam(Team $team, int $id): ?Communication
    {
        return Communication::query()
            ->where('team_id', $team->id)
            ->whereKey($id)
            ->first();
    }

    private function resolveTeam(Request $request): Team|JsonResponse
    {
        $fromToken = $request->attributes->get('team');
        if ($fromToken instanceof Team)
        {
            $this->ensureTeamModule($fromToken, 'communications');

            return $fromToken;
        }

        return $this->teamOrError($request);
    }

    private function plainTeamApiToken(Team $team): ?string
    {
        $tokens = $team->getApiTokens();
        $plainToken = $tokens[0]['plain'] ?? $team->getSetting('api_token_plain', null);
        if (is_string($plainToken) && $plainToken !== '')
        {
            return $plainToken;
        }

        if ($tokens !== [] || $team->getSetting('api_token_hash'))
        {
            $created = $team->createApiToken('API Access Token', '*');

            return $created['plain'];
        }

        return null;
    }
}
