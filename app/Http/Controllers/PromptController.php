<?php

namespace App\Http\Controllers;

use App\DataTables\PromptDataTable;
use App\Models\Prompt;
use App\Models\PromptType;
use Illuminate\Http\Request;
use stdClass;
use Carbon\Carbon;
use Log;

class PromptController extends Controller
{
    public function index(PromptDataTable $dataTable)
    {
        return $dataTable->render('prompt.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = new stdClass();
        $data->types = PromptType::getOptions();

        return view('prompt.form', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:25',
            'content' => 'required|string|min:10',
        ]);

        $data['status'] = $request->has('status') ? 1 : 0;

        Prompt::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'type_id' => $data['type_id'],
                'content' => $data['content'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-prompt-list')->with('success', 'Record saved successfully.');
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
        $data = Prompt::find($id);
        $data->types = PromptType::getOptions();

        if (!$data)
        {
            return redirect()->route('app-prompt-list')->with('error', 'Record not found.');
        }

        return view('prompt.form', compact('data'));
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
        $model = Prompt::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
