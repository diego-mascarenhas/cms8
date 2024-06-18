<?php

namespace App\Http\Controllers;

use App\DataTables\TemplateDataTable;
use App\Models\Template;
use Illuminate\Http\Request;
use stdClass;

class TemplateController extends Controller
{
    public function index(TemplateDataTable $dataTable)
    {
        return $dataTable->render('template.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('template.form');
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

        Template::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-mkt-template-list')->with('success', 'Registro guardado correctamente.');
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
        $data = Template::find($id);

        if (!$data)
        {
            return redirect()->route('app-mkt-template-list')->with('error', 'Template not found.');
        }

        return view('template.form', compact('data'));
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
        $model = Template::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'El registro ha sido eliminado.'], 200);
    }
}
