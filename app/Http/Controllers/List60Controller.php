<?php

namespace App\Http\Controllers;

use App\DataTables\List60DataTable;
use App\Http\Requests\SendContactOutreachRequest;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\User;
use App\Services\ContactOutreachService;
use App\Support\List60NextContactDate;
use App\Support\List60StatusAdvancer;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try
        {
            if (! $request->has('contact_id'))
            {
                return response()->json([
                    'error' => 'El ID del contacto es requerido',
                ], 400);
            }

            $totalContacts = List60::withoutGlobalScope('responsible')
                ->join('contacts', 'list60.contact_id', '=', 'contacts.id')
                ->where('contacts.team_id', auth()->user()->currentTeam->id)
                ->where('list60.responsible_id', auth()->id())
                ->count();
            if ($totalContacts >= 60)
            {
                return response()->json([
                    'error' => 'La lista ya tiene 60 contactos',
                ], 400);
            }

            $existingContact = List60::where('contact_id', $request->contact_id)->first();
            if ($existingContact)
            {
                return response()->json([
                    'error' => 'El contacto ya está en la Lista de 60',
                ], 400);
            }

            $list60 = new List60;
            $list60->contact_id = $request->contact_id;
            $list60->date_next = List60NextContactDate::afterOutreach();
            $list60->responsible_id = auth()->id();
            $list60->status_id = List60StatusAdvancer::initialStatusId();
            $list60->save();

            $followingStatus = ContactStatus::query()->where('name', 'En seguimiento')->first();
            if ($followingStatus)
            {
                Contact::query()
                    ->where('id', $request->contact_id)
                    ->update(['status_id' => $followingStatus->id]);
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
            $target = User::where('id', $request->responsible_id)
                ->whereHas('teams', function ($q)
                {
                    $q->where('team_id', auth()->user()->currentTeam->id);
                })
                ->whereHas('roles', function ($q)
                {
                    $q->whereIn('name', ['admin', 'collaborator', 'employee']);
                })
                ->first();

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
        $record = List60::with('contact')->findOrFail($id);

        if (! $record->contact)
        {
            return response()->json(['error' => 'Contacto no encontrado'], 404);
        }

        $user = auth()->user();
        if (! $user->hasRole('admin') && $record->responsible_id !== $user->id)
        {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validated();

        $interaction = $outreach->send(
            $user,
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
}
