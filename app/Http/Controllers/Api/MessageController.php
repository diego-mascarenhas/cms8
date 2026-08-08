<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageApiRequest;
use App\Http\Requests\Api\TestMessageApiRequest;
use App\Http\Requests\Api\UpdateMessageApiRequest;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Team;
use App\Services\Mail\CampaignMessageApiService;
use App\Services\Mail\MessageCampaignActivationService;
use App\Services\Mail\MessageCampaignTestSendService;
use App\Support\MessageTemplateMergeFields;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    use ChecksTeamModule;

    public function __construct(
        private CampaignMessageApiService $campaignMessages,
        private MessageCampaignActivationService $activation,
        private MessageCampaignTestSendService $testSend,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? CampaignMessageApiService::PER_PAGE);

        $paginator = $this->campaignMessages->paginate($team, $search, $page, $perPage);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        $items = $paginator->getCollection()
            ->map(fn ($message) => $this->campaignMessages->formatForList($message))
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

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = $this->campaignMessages->findForTeam($team, $id);

        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->campaignMessages->formatForShow($message, $team),
        ]);
    }

    public function store(StoreMessageApiRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = $this->persistMessage($request, $team, null);

        return response()->json([
            'success' => true,
            'data' => $this->campaignMessages->formatForShow(
                $this->campaignMessages->findForTeam($team, $message->id),
                $team,
            ),
        ], 201);
    }

    public function update(UpdateMessageApiRequest $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $existing = Message::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $existing)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        $message = $this->persistMessage($request, $team, $existing);

        return response()->json([
            'success' => true,
            'data' => $this->campaignMessages->formatForShow(
                $this->campaignMessages->findForTeam($team, $message->id),
                $team,
            ),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = Message::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => __('Message deleted'),
        ]);
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = Message::query()
            ->with(['deliveries', 'team.settings', 'campaigns'])
            ->where('team_id', $team->id)
            ->find($id);

        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        $result = $this->activation->activate($message, $team);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success']
                ? $this->campaignMessages->formatForShow(
                    $this->campaignMessages->findForTeam($team, $message->id),
                    $team,
                )
                : null,
        ], $result['success'] ? 200 : 400);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = Message::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        $result = $this->activation->pause($message);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success']
                ? $this->campaignMessages->formatForShow(
                    $this->campaignMessages->findForTeam($team, $message->id),
                    $team,
                )
                : null,
        ], $result['success'] ? 200 : 500);
    }

    public function test(TestMessageApiRequest $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = Message::query()
            ->with(['template', 'team.settings'])
            ->where('team_id', $team->id)
            ->find($id);

        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        $email = (string) $request->validated('email');

        try
        {
            $this->testSend->send($message, $request->user(), $team, [$email]);

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado exitosamente',
                'email' => $email,
            ]);
        } catch (\Exception $e)
        {
            Log::error('API test message send failed', [
                'message_id' => $id,
                'team_id' => $team->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('No se pudo enviar el email de prueba. Por favor, contacte con soporte técnico si el problema persiste.'),
            ], 500);
        }
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $message = Message::query()
            ->with(['template', 'category'])
            ->where('team_id', $team->id)
            ->find($id);

        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Message not found'),
            ], 404);
        }

        $sampleContact = $message->audienceContactsQuery()->first()
            ?? MessageTemplateMergeFields::sampleContact();

        $htmlContent = $message->resolveMailHtml();
        if (trim($htmlContent) !== '')
        {
            $htmlContent = MessageTemplateMergeFields::replace($htmlContent, $sampleContact);
        } else
        {
            $previewText = filled($message->text)
                ? MessageTemplateMergeFields::replace((string) $message->text, $sampleContact)
                : '';
            $htmlContent = '<p>'.e($previewText).'</p>';
        }

        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $advertisingFooter = $team->getAdvertisingFooter();
        if ($advertisingFooter)
        {
            if (stripos($htmlContent, '</body>') !== false)
            {
                $htmlContent = str_ireplace('</body>', $advertisingFooter.'</body>', $htmlContent);
            } else
            {
                $htmlContent .= $advertisingFooter;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'html' => $htmlContent,
                'subject' => MessageTemplateMergeFields::replace((string) $message->name, $sampleContact),
                'text' => filled($message->text)
                    ? MessageTemplateMergeFields::replace((string) $message->text, $sampleContact)
                    : null,
            ],
        ]);
    }

    private function persistMessage(StoreMessageApiRequest|UpdateMessageApiRequest $request, Team $team, ?Message $existing): Message
    {
        $validated = $request->validated();
        $hasDeliveries = $existing !== null
            && MessageDelivery::query()->where('message_id', $existing->id)->exists();

        $typeId = (int) ($validated['type_id'] ?? $existing?->type_id ?? 1);
        $templateId = array_key_exists('template_id', $validated)
            ? (filled($validated['template_id'] ?? null) ? (int) $validated['template_id'] : null)
            : $existing?->template_id;

        $weekdays = null;
        if (array_key_exists('send_allowed_weekdays', $validated) && is_array($validated['send_allowed_weekdays']))
        {
            $weekdaysSorted = array_values(array_unique(array_map('intval', $validated['send_allowed_weekdays'])));
            sort($weekdaysSorted);
            $weekdays = $weekdaysSorted === range(1, 7) ? null : $weekdaysSorted;
        } elseif ($existing !== null)
        {
            $weekdays = $existing->send_allowed_weekdays;
        }

        $mailHtml = null;
        if ($typeId === 1 && ! $hasDeliveries)
        {
            if (array_key_exists('mail_html', $validated) && is_string($validated['mail_html']) && trim($validated['mail_html']) !== '')
            {
                $mailHtml = $validated['mail_html'];
            } elseif (array_key_exists('template_html', $validated) && is_string($validated['template_html']) && trim($validated['template_html']) !== '')
            {
                $mailHtml = $validated['template_html'];
            } elseif ($templateId !== null && $templateId > 0)
            {
                $template = \App\Models\Template::query()
                    ->where('team_id', $team->id)
                    ->find($templateId);
                if ($template)
                {
                    $mailHtml = $template->html !== '' ? $template->html : null;
                }
            }
        }

        $messageModel = null;

        DB::transaction(function () use ($request, $validated, $team, $existing, $typeId, $templateId, $weekdays, $mailHtml, $hasDeliveries, &$messageModel): void
        {
            $payload = [
                'team_id' => $team->id,
                'type_id' => $typeId,
            ];

            foreach (['name', 'text', 'contact_status_id', 'send_window_start', 'send_window_end'] as $field)
            {
                if (array_key_exists($field, $validated))
                {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('template_id', $validated))
            {
                $payload['template_id'] = $templateId;
            }

            if ($request->has('status_id'))
            {
                $payload['status_id'] = $request->boolean('status_id') ? 1 : 0;
            } elseif ($existing === null)
            {
                $payload['status_id'] = 0;
            }

            foreach (['show_unsubscribe', 'enable_open_tracking', 'enable_click_tracking'] as $flag)
            {
                if ($request->has($flag))
                {
                    $payload[$flag] = $request->boolean($flag) ? 1 : 0;
                } elseif ($existing === null)
                {
                    $payload[$flag] = 1;
                }
            }

            if (array_key_exists('min_hours_between_emails', $validated))
            {
                $payload['min_hours_between_emails'] = max(0, (int) round((float) $validated['min_hours_between_emails']));
            } elseif ($existing === null)
            {
                $payload['min_hours_between_emails'] = 48;
            }

            if (array_key_exists('send_allowed_weekdays', $validated) || $existing === null)
            {
                $payload['send_allowed_weekdays'] = $weekdays;
            }

            if (array_key_exists('scheduled_send_at', $validated))
            {
                $payload['scheduled_send_at'] = filled($validated['scheduled_send_at'] ?? null)
                    ? Carbon::parse($validated['scheduled_send_at'], config('app.timezone'))->utc()
                    : null;
            }

            if ($mailHtml !== null && trim($mailHtml) !== '')
            {
                $payload['mail_html'] = $mailHtml;
            }

            if ($existing)
            {
                $existing->fill($payload);
                $existing->save();
                $messageModel = $existing;
            } else
            {
                $messageModel = Message::create($payload);
            }

            if (! $hasDeliveries && array_key_exists('message_category_ids', $validated))
            {
                $messageModel->syncMessageCategories($validated['message_category_ids'] ?? []);
            }
        });

        return $messageModel->fresh();
    }
}
