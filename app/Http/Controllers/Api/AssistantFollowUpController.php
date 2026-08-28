<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FinishFollowUpContactRequest;
use App\Http\Requests\Api\SummarizeFollowUpContactRequest;
use App\Http\Requests\Api\UpdateList60ResponsibleRequest;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\User;
use App\Services\InboxConversationSummaryService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class AssistantFollowUpController extends Controller
{
    public function updateResponsible(UpdateList60ResponsibleRequest $request, int $id): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $validated = $request->validated();

        $record = List60::query()
            ->whereHas('contact', function ($query) use ($team): void
            {
                $query->withoutGlobalScopes()->where('team_id', $team->id);
            })
            ->findOrFail($id);

        $user = $request->user();
        if (! $user->hasRole('admin') && (int) $record->responsible_id !== (int) $user->id)
        {
            return response()->json([
                'success' => false,
                'message' => __('No autorizado'),
            ], 403);
        }

        $responsible = $this->teamAdvisor($team->id, (int) $team->user_id, (int) $validated['responsible_id']);
        if ($responsible === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('Usuario no válido para este equipo'),
            ], 422);
        }

        $record->responsible_id = $responsible->id;
        $record->save();

        return response()->json([
            'success' => true,
            'responsible_id' => (int) $responsible->id,
            'responsible_name' => $responsible->name,
        ]);
    }

    public function finish(FinishFollowUpContactRequest $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $validated = $request->validated();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->findOrFail((int) $validated['contact_id']);

        $this->authorize('update', $contact);

        $lost = ContactStatus::query()->where('name', 'Perdido')->first();
        if ($lost === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No se encontró el estado Perdido.'),
            ], 422);
        }

        $contact->update(['status_id' => $lost->id]);

        return response()->json([
            'success' => true,
            'contact_id' => $contact->id,
            'status_id' => $lost->id,
        ]);
    }

    public function summarize(
        SummarizeFollowUpContactRequest $request,
        InboxConversationSummaryService $summaries,
    ): JsonResponse {
        $team = $request->user()?->currentTeam;
        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->findOrFail((int) $request->validated()['contact_id']);

        $this->authorize('update', $contact);

        try
        {
            $result = $summaries->summarize($team, $contact);
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'contact_id' => $contact->id,
            'summary' => $result['summary'],
            'intent' => $result['intent'],
        ]);
    }

    private function teamAdvisor(int $teamId, int $ownerId, int $userId): ?User
    {
        $teamUserIds = User::query()
            ->whereHas('teams', function ($query) use ($teamId): void
            {
                $query->where('team_id', $teamId);
            })
            ->pluck('id')
            ->all();
        $teamUserIds[] = $ownerId;
        $teamUserIds = array_values(array_unique(array_map('intval', $teamUserIds)));

        if (! in_array($userId, $teamUserIds, true))
        {
            return null;
        }

        if ($userId === $ownerId)
        {
            return User::query()->find($userId);
        }

        return User::query()
            ->where('id', $userId)
            ->whereHas('roles', function ($query): void
            {
                $query->whereIn('name', ['admin', 'collaborator', 'employee']);
            })
            ->first();
    }
}
