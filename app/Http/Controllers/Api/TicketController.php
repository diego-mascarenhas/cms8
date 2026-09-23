<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddTicketResponseRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\RateTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketPriorityRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketRating;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    use ChecksTeamModule;

    public function index(Request $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $user = $request->user();
        if (! $user->can('viewAny', Ticket::class) && ! $user->can('create', Ticket::class))
        {
            return response()->json([
                'success' => false,
                'message' => __('This action is unauthorized.'),
            ], 403);
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:open,in_progress,waiting_client,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned' => 'nullable|in:me,unassigned',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Ticket::query()
            ->where('team_id', $team->id)
            ->with(['user', 'assignedTo'])
            ->withCount('responses')
            ->latest('id');

        if (! $user->can('viewAny', Ticket::class))
        {
            $query->where(function ($builder) use ($user)
            {
                $builder->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        if (! empty($validated['status']))
        {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['priority']))
        {
            $query->where('priority', $validated['priority']);
        }

        if (($validated['assigned'] ?? null) === 'me')
        {
            $query->where('assigned_to', $user->id);
        } elseif (($validated['assigned'] ?? null) === 'unassigned')
        {
            $query->whereNull('assigned_to');
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            $query->where(function ($builder) use ($search)
            {
                $builder->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()
                ->map(fn (Ticket $ticket) => $this->formatTicket($ticket, false))
                ->values()
                ->all(),
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

        $user = $request->user();
        $base = Ticket::query()->where('team_id', $team->id);
        if (! $user->can('viewAny', Ticket::class))
        {
            $base->where(function ($builder) use ($user)
            {
                $builder->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) $counts->sum(),
                'open' => (int) ($counts['open'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'waiting_client' => (int) ($counts['waiting_client'] ?? 0),
                'closed' => (int) ($counts['closed'] ?? 0),
                'mine' => (int) (clone $base)->where('assigned_to', $user->id)->where('status', '!=', 'closed')->count(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $this->authorize('view', $ticket);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($ticket, true, $request->user()),
        ]);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = Ticket::create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'subject' => $request->string('subject')->toString(),
            'description' => $request->string('description')->toString(),
            'priority' => $request->string('priority')->toString(),
            'status' => 'open',
        ]);

        $this->storeAttachments($ticket, $request);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ], 201);
    }

    public function update(UpdateTicketRequest $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $ticket->update([
            'subject' => $request->string('subject')->toString(),
            'description' => $request->string('description')->toString(),
        ]);
        $this->storeAttachments($ticket, $request);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function addResponse(AddTicketResponseRequest $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $isInternalNote = $request->boolean('is_internal_note') && $this->canSeeInternalNotes($request->user());

        $response = TicketResponse::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->string('message')->toString(),
            'is_internal_note' => $isInternalNote,
        ]);

        $this->storeAttachments($response, $request);

        if (! $isInternalNote)
        {
            if (in_array($ticket->status, ['open', 'waiting_client'], true))
            {
                $ticket->update(['status' => 'in_progress']);
            } elseif ($ticket->status === 'in_progress')
            {
                $ticket->update(['status' => 'waiting_client']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $data = ['status' => $request->string('status')->toString()];
        if ($data['status'] === 'closed')
        {
            $data['closed_at'] = now();
        } elseif ($ticket->status === 'closed')
        {
            $data['closed_at'] = null;
        }

        $ticket->update($data);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function assign(AssignTicketRequest $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $assignedTo = $request->input('assigned_to');
        if ($assignedTo)
        {
            $belongsToTeam = $team->allUsers()->contains('id', (int) $assignedTo);
            if (! $belongsToTeam)
            {
                return response()->json([
                    'success' => false,
                    'message' => __('The selected user is not on this team.'),
                ], 422);
            }
        }

        $ticket->update([
            'assigned_to' => $assignedTo ?: null,
            'status' => $assignedTo ? 'in_progress' : 'open',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function updatePriority(UpdateTicketPriorityRequest $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $ticket->update(['priority' => $request->string('priority')->toString()]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function close(int $id, Request $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);
        $this->authorize('update', $ticket);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function rate(RateTicketRequest $request, int $id): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $ticket = $this->findTicket($team, $id);

        if ($ticket->status !== 'closed')
        {
            return response()->json([
                'success' => false,
                'message' => __('tickets.Only closed tickets can be rated.'),
            ], 422);
        }

        if ($ticket->rating)
        {
            return response()->json([
                'success' => false,
                'message' => __('tickets.This ticket has already been rated.'),
            ], 422);
        }

        TicketRating::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'tiempo_respuesta' => $request->integer('tiempo_respuesta'),
            'atencion' => $request->integer('atencion'),
            'solucion' => $request->integer('solucion'),
            'comentarios' => $request->input('comentarios'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTicket($this->findTicket($team, $ticket->id), true, $request->user()),
        ]);
    }

    public function downloadAttachment(Request $request, int $id, int $media): BinaryFileResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            abort($team->getStatusCode(), (string) $team->getData()->message);
        }

        $ticket = $this->findTicket($team, $id);
        $this->authorize('view', $ticket);

        $file = $this->findAttachment($ticket, $media, $request->user());
        if (! $file)
        {
            abort(404, __('tickets.File not found'));
        }

        if (str_starts_with((string) $file->mime_type, 'image/'))
        {
            return response()->file($file->getPath());
        }

        return response()->download($file->getPath(), $file->file_name);
    }

    private function resolveTeam(Request $request): Team|JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->ensureTeamModule($team, 'tickets');

        return $team;
    }

    private function findTicket(Team $team, int $id): Ticket
    {
        return Ticket::query()
            ->where('team_id', $team->id)
            ->with(['user', 'assignedTo', 'rating.user', 'responses.user', 'media', 'responses.media'])
            ->withCount('responses')
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTicket(Ticket $ticket, bool $detailed, ?User $viewer = null): array
    {
        $payload = [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'user' => $this->formatUser($ticket->user),
            'assigned_to' => $this->formatUser($ticket->assignedTo),
            'responses_count' => (int) ($ticket->responses_count ?? $ticket->responses->count()),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'last_response_at' => $ticket->last_response_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if (! $detailed)
        {
            return $payload;
        }

        $seeInternal = $viewer && $this->canSeeInternalNotes($viewer);
        $responses = $ticket->responses
            ->filter(fn (TicketResponse $response) => $seeInternal || ! $response->is_internal_note)
            ->values()
            ->map(fn (TicketResponse $response) => [
                'id' => $response->id,
                'message' => $response->message,
                'is_internal_note' => (bool) $response->is_internal_note,
                'user' => $this->formatUser($response->user),
                'attachments' => $response->getMedia('attachments')->map(fn (Media $media) => $this->formatMedia($media))->values()->all(),
                'created_at' => $response->created_at?->toIso8601String(),
            ])
            ->all();

        $payload['attachments'] = $ticket->getMedia('attachments')
            ->map(fn (Media $media) => $this->formatMedia($media))
            ->values()
            ->all();
        $payload['responses'] = $responses;
        $payload['rating'] = $ticket->rating ? [
            'tiempo_respuesta' => $ticket->rating->tiempo_respuesta,
            'atencion' => $ticket->rating->atencion,
            'solucion' => $ticket->rating->solucion,
            'promedio' => $ticket->rating->promedio,
            'comentarios' => $ticket->rating->comentarios,
            'user' => $this->formatUser($ticket->rating->user),
        ] : null;
        $payload['permissions'] = [
            'update' => $viewer?->can('update', $ticket) ?? false,
            'delete' => $viewer?->can('delete', $ticket) ?? false,
            'internal_note' => $viewer ? $this->canSeeInternalNotes($viewer) : false,
        ];

        return $payload;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function formatUser(?User $user): ?array
    {
        if (! $user)
        {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    /**
     * @return array{id: int, file_name: string, mime_type: string|null, size: int}
     */
    private function formatMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }

    private function storeAttachments(Ticket|TicketResponse $model, Request $request): void
    {
        if (! $request->hasFile('attachments'))
        {
            return;
        }

        foreach ($request->file('attachments') as $file)
        {
            $model->addMedia($file)->toMediaCollection('attachments');
        }
    }

    private function findAttachment(Ticket $ticket, int $mediaId, User $viewer): ?Media
    {
        $media = $ticket->getMedia('attachments')->firstWhere('id', $mediaId);
        if ($media)
        {
            return $media;
        }

        $seeInternal = $this->canSeeInternalNotes($viewer);
        foreach ($ticket->responses as $response)
        {
            if ($response->is_internal_note && ! $seeInternal)
            {
                continue;
            }

            $media = $response->getMedia('attachments')->firstWhere('id', $mediaId);
            if ($media)
            {
                return $media;
            }
        }

        return null;
    }

    private function canSeeInternalNotes(User $user): bool
    {
        return $user->hasRole(['admin', 'editor', 'technical', 'developer', 'collaborator']);
    }
}
