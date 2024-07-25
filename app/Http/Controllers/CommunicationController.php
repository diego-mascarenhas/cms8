<?php

namespace App\Http\Controllers;

use App\DataTables\CommunicationDataTable;
use App\Models\Communication;
use Illuminate\Http\Request;
use stdClass;
use Carbon\Carbon;
use Log;

class CommunicationController extends Controller
{
    public function index(CommunicationDataTable $dataTable)
    {
        return $dataTable->render('communication.index');
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
        $model = Communication::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
