<?php

namespace App\Http\Controllers;

use App\DataTables\List60DataTable;
use App\Models\List60;
use Illuminate\Http\Request;

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
        //
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
