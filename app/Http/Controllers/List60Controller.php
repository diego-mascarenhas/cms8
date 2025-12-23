<?php

namespace App\Http\Controllers;

use App\DataTables\List60DataTable;
use App\Models\List60;
use App\Models\User;
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

            $nextDate = now();
            $businessDays = 0;

            while ($businessDays < 7)
            {
                $nextDate = $nextDate->addDay();
                if (! $nextDate->isWeekend())
                {
                    $businessDays++;
                }
            }

            $list60 = new List60;
            $list60->contact_id = $request->contact_id;
            $list60->date_next = $nextDate;
            $list60->responsible_id = auth()->id();
            $list60->save();

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
            'responsible_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'date_next' => ['nullable', 'date'],
        ]);

        $record = List60::with('contact')->findOrFail($id);

        // Only admins or current responsible can reassign
        $user = auth()->user();
        if (! $user->hasRole('admin') && $record->responsible_id !== $user->id)
        {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Ensure target user belongs to current team and has allowed role
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
        if ($request->filled('date_next'))
        {
            $record->date_next = Carbon::parse($request->date_next);
        }
        $record->save();

        return response()->json([
            'success' => 'Asignación actualizada',
            'responsible_name' => $target->name,
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
}
