<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Models\Category;
use App\Models\Message;
use App\Models\MessageType;
use App\Models\Template;
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
        $data = new stdClass();
        $data->categories = Category::categories();
        $data->types = MessageType::types();
        $data->templates = Template::templates();

        return view('message.form', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:25',
            'text' => 'required|string|min:3|max:255',
        ]);

        $data['status'] = $request->has('status') ? 1 : 0;

        Message::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'type_id' => $data['type_id'],
                'text' => $data['text'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-mkt-message-list')->with('success', 'Record saved successfully.');
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
        $data->categories = Category::categories();
        $data->types = MessageType::types();
        $data->templates = Template::templates();

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

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
