<?php

namespace App\Http\Controllers;

use App\DataTables\List60DataTable;
use App\Http\Requests\SendContactOutreachRequest;
use App\Http\Requests\StoreList60Request;
use App\Http\Requests\SuggestList60OutreachRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\User;
use App\Services\ContactOutreachService;
use App\Services\List60OutreachSuggestionService;
use App\Support\List60NextContactDate;
use App\Support\List60StatusAdvancer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class List60Controller extends Controller
{
    public function index(List60DataTable $dataTable)
    {
        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $teamUsers = User::query()
            ->whereHas('teams', function ($q)
            {
                $q->where('team_id', auth()->user()->currentTeam->id);
            })
            ->whereHas('roles', function ($q)
            {
                $q->whereIn('name', ['admin', 'collaborator', 'employee']);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $dataTable->teamUsers = $teamUsers;

        return $dataTable->render('list60.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function prefill(Contact $contact)
    {
        $this->authorize('update', $contact);

        $contact->load('categories:id');

        $notes = '';
        if (is_object($contact->data) && isset($contact->data->notes))
        {
            $notes = (string) $contact->data->notes;
        }

        return response()->json([
            'id' => $contact->id,
            'name' => $contact->name,
            'notes' => $notes,
            'category_ids' => $contact->categories->pluck('id')->values()->all(),
        ]);
    }

    public function outreachContext(string $id): JsonResponse
    {
        $record = $this->findOutreachRecord($id);
        $contact = $record->contact;

        $notes = '';
        if (is_object($contact->data) && isset($contact->data->notes))
        {
            $notes = (string) $contact->data->notes;
        }

        $sentiment = null;
        $currentSentiment = $contact->currentSentiment;
        if ($currentSentiment?->sentiment)
        {
            $sentiment = [
                'name' => $currentSentiment->sentiment->name,
                'emoji' => $currentSentiment->sentiment->emoji,
                'notes' => $currentSentiment->notes,
            ];
        }

        return response()->json([
            'contact_id' => $contact->id,
            'contact_name' => $contact->name,
            'notes' => $notes,
            'categories' => $contact->categories->pluck('name')->values()->all(),
            'sentiment' => $sentiment,
            'can_whatsapp' => (bool) $contact->getWhatsAppNumber(),
            'can_email' => is_string($contact->email)
                && $contact->email !== ''
                && filter_var($contact->email, FILTER_VALIDATE_EMAIL),
        ]);
    }

    public function suggestOutreach(
        SuggestList60OutreachRequest $request,
        string $id,
        List60OutreachSuggestionService $suggestions,
    ): JsonResponse {
        $record = $this->findOutreachRecord($id);
        $contact = $record->contact;
        $channel = $request->validated('channel');

        $notes = '';
        if (is_object($contact->data) && isset($contact->data->notes))
        {
            $notes = (string) $contact->data->notes;
        }

        $sentiment = null;
        $currentSentiment = $contact->currentSentiment;
        if ($currentSentiment?->sentiment)
        {
            $sentiment = [
                'name' => $currentSentiment->sentiment->name,
                'emoji' => $currentSentiment->sentiment->emoji,
                'notes' => $currentSentiment->notes,
            ];
        }

        $result = $suggestions->suggest(
            auth()->user(),
            $contact,
            $channel,
            $notes,
            $sentiment,
            $contact->categories->pluck('name')->all(),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('Error'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'message' => $result['message'] ?? '',
            'subject' => $result['subject'] ?? '',
            'body' => $result['body'] ?? ($result['message'] ?? ''),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreList60Request $request)
    {
        try
        {
            $validated = $request->validated();
            $contact = Contact::query()->findOrFail($validated['contact_id']);
            $this->authorize('update', $contact);

            $responsible = $this->resolveResponsibleUser($request->input('responsible_id'));
            if (! $responsible)
            {
                return response()->json([
                    'error' => 'Usuario no válido para este equipo',
                ], 422);
            }

            $totalContacts = List60::withoutGlobalScope('responsible')
                ->join('contacts', 'list60.contact_id', '=', 'contacts.id')
                ->where('contacts.team_id', auth()->user()->currentTeam->id)
                ->where('list60.responsible_id', $responsible->id)
                ->count();
            if ($totalContacts >= 60)
            {
                return response()->json([
                    'error' => 'La lista ya tiene 60 contactos',
                ], 400);
            }

            $existingContact = List60::where('contact_id', $contact->id)->first();
            if ($existingContact)
            {
                return response()->json([
                    'error' => 'El contacto ya está en la Lista de 60',
                ], 400);
            }

            $list60 = new List60;
            $list60->contact_id = $contact->id;
            $list60->date_next = List60NextContactDate::afterOutreach();
            $list60->responsible_id = $responsible->id;
            $list60->status_id = List60StatusAdvancer::initialStatusId();
            $list60->save();

            if (array_key_exists('category_ids', $validated))
            {
                $categoryIds = Category::onlyExistingIds($validated['category_ids'] ?? []);
                $contact->categories()->sync($categoryIds);
            }

            if (array_key_exists('notes', $validated))
            {
                $contactData = (array) ($contact->data ?? new \stdClass);
                $contactData['notes'] = $validated['notes'] ?? '';
                $contact->update(['data' => $contactData]);
            }

            $followingStatus = ContactStatus::query()->where('name', 'En seguimiento')->first();
            if ($followingStatus)
            {
                $contact->update(['status_id' => $followingStatus->id]);
            }

            return response()->json([
                'success' => 'Contacto agregado exitosamente a la Lista de 60',
            ], 200);
        } catch (\Exception $e)
        {
            \Log::error('Error al agregar contacto a Lista60: '.$e->getMessage());

            return response()->json([
                'error' => 'No se pudo agregar el contacto a la Lista de 60: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'responsible_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')],
            'date_next' => ['sometimes', 'nullable', 'date'],
        ]);

        $record = List60::with('contact')->findOrFail($id);

        // Only admins or current responsible can update
        $user = auth()->user();
        if (! $user->hasRole('admin') && $record->responsible_id !== $user->id)
        {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($request->has('responsible_id'))
        {
            $target = $this->resolveResponsibleUser($request->responsible_id);
            if (! $target)
            {
                return response()->json(['error' => 'Usuario no válido para este equipo'], 422);
            }

            $record->responsible_id = $target->id;
        }

        if ($request->filled('date_next'))
        {
            $record->date_next = Carbon::parse($request->date_next);
        }

        $record->save();

        return response()->json([
            'success' => 'Asignación actualizada',
            'responsible_name' => $record->responsible?->name,
            'date_next' => $record->date_next ? Carbon::parse($record->date_next)->format('Y-m-d') : null,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = List60::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'El contacto se ha eliminado de la Lista de 60'], 200);
    }

    public function sendOutreach(SendContactOutreachRequest $request, string $id, ContactOutreachService $outreach)
    {
        $record = $this->findOutreachRecord($id);

        $validated = $request->validated();

        $interaction = $outreach->send(
            auth()->user(),
            $record->contact,
            $validated['channel'],
            $validated['message'],
            $validated['subject'] ?? null,
        );

        $record->date_next = List60NextContactDate::afterOutreach();
        $record->status_id = List60StatusAdvancer::statusIdAfterOutreach((int) $record->status_id);
        $record->save();

        return response()->json([
            'success' => __('app.list60_outreach_success'),
            'interaction_id' => $interaction->id,
            'date_next' => $record->date_next->format('Y-m-d'),
        ]);
    }

    private function resolveResponsibleUser(mixed $responsibleId): ?User
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if (! $team)
        {
            return null;
        }

        $targetId = ($user->hasRole('admin') && filled($responsibleId))
            ? (int) $responsibleId
            : (int) $user->id;

        $teamUserIds = User::query()
            ->whereHas('teams', function ($q) use ($team)
            {
                $q->where('team_id', $team->id);
            })
            ->pluck('id')
            ->all();

        $teamUserIds[] = (int) $team->user_id;
        $teamUserIds = array_values(array_unique($teamUserIds));

        if (! in_array($targetId, $teamUserIds, true))
        {
            return null;
        }

        return User::query()
            ->where('id', $targetId)
            ->whereHas('roles', function ($q)
            {
                $q->whereIn('name', ['admin', 'collaborator', 'employee']);
            })
            ->first();
    }

    private function findOutreachRecord(string $id): List60
    {
        $record = List60::query()
            ->with([
                'contact.categories',
                'contact.currentSentiment.sentiment',
            ])
            ->findOrFail($id);

        $contact = $record->contact;
        if (! $contact)
        {
            abort(404);
        }

        Gate::authorize('logInteraction', $contact);

        $user = auth()->user();
        if (! $user->hasRole('admin') && $record->responsible_id !== $user->id)
        {
            abort(403);
        }

        return $record;
    }
}
