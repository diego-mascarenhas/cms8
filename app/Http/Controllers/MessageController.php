<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Models\Message;
use Illuminate\Http\Request;
use stdClass;

class MessageController extends Controller
{
    public function index(MessageDataTable $dataTable)
    {
        return $dataTable->render('message.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('message.form');
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

        Message::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-mkt-message-list')->with('success', 'Registro guardado correctamente.');
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
        $data = Message::find($id);

        if (!$data)
        {
            return redirect()->route('app-mkt-message-list')->with('error', 'Message not found.');
        }

        return view('message.form', compact('data'));
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
        $model = Message::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'El registro ha sido eliminado.'], 200);
    }
}
