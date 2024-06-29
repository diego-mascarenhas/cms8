<?php

namespace App\Http\Controllers;

use App\DataTables\ClientDataTable;
use App\Models\Client;
use Illuminate\Http\Request;
use Dotlogics\Grapesjs\App\Traits\EditorTrait;

class ClientController extends Controller
{
    use EditorTrait;

    public function index(ClientDataTable $dataTable)
    {
        return $dataTable->render('client.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('client.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:25',
        ]);

        $data['status'] = $request->has('status') ? 1 : 0;

        Client::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-client-list')->with('success', 'Record saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $page = Client::find($id);

        if (!$page) {
            return redirect()->route('client.index')->with('error', 'Page not found.');
        }

        return view('page.show', compact('page'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Client::find($id);

        if (!$data)
        {
            return redirect()->route('app-client-list')->with('error', 'Client not found.');
        }

        return view('client.form', compact('data'));
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
        $model = Client::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    public function editor(Request $request, Client $page)
    {
        return $this->show_gjs_editor($request, $page);
    }
}
