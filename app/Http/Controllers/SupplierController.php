<?php

namespace App\Http\Controllers;

use App\DataTables\SupplierDataTable;
use App\Models\Enterprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    public function index(SupplierDataTable $dataTable)
    {
        return $dataTable->render('supplier.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('supplier.form');
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

        Enterprise::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-supplier-list')->with('success', 'Record saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $page = Enterprise::find($id);

        if (!$page)
        {
            return redirect()->route('supplier.index')->with('error', 'Page not found.');
        }

        return view('page.show', compact('page'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Enterprise::find($id);

        if (!$data)
        {
            return redirect()->route('app-supplier-list')->with('error', 'Client not found.');
        }

        return view('supplier.form', compact('data'));
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
        $model = Enterprise::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
