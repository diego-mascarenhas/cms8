<?php

namespace App\Http\Controllers;

use App\DataTables\List60DataTable;
use App\Models\List60;
use Illuminate\Http\Request;

use Log;

class List60Controller extends Controller
{
    public function index(List60DataTable $dataTable)
    {
        if (!auth()->user()->currentTeam)
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
            if (!$request->has('contact_id'))
            {
                return response()->json([
                    'error' => 'El ID del contacto es requerido'
                ], 400);
            }

            $totalContacts = List60::join('contacts', 'list60.contact_id', '=', 'contacts.id')
                ->where('contacts.team_id', auth()->user()->currentTeam->id)
                ->count();
            if ($totalContacts >= 60)
            {
                return response()->json([
                    'error' => 'La lista ya tiene 60 contactos'
                ], 400);
            }

            $existingContact = List60::where('contact_id', $request->contact_id)->first();
            if ($existingContact)
            {
                return response()->json([
                    'error' => 'El contacto ya está en la Lista de 60'
                ], 400);
            }

            $nextDate = now();
            $businessDays = 0;
            while ($businessDays < 7)
            {
                $nextDate = $nextDate->addDay();
                if (!$nextDate->isWeekend())
                {
                    $businessDays++;
                }
            }

            $list60 = new List60();
            $list60->contact_id = $request->contact_id;
            $list60->date_next = $nextDate;
            $list60->save();

            return response()->json([
                'success' => 'Contacto agregado exitosamente a la Lista de 60'
            ], 200);

        }
        catch (\Exception $e)
        {
            \Log::error('Error al agregar contacto a Lista60: ' . $e->getMessage());
            return response()->json([
                'error' => 'No se pudo agregar el contacto a la Lista de 60: ' . $e->getMessage()
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
        //
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
