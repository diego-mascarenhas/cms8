<?php

namespace App\Http\Controllers;

use App\DataTables\CategoryDataTable;
use App\Models\Category;
use Illuminate\Http\Request;
use stdClass;

class CategoryController extends Controller
{
    public function index(CategoryDataTable $dataTable)
    {
        return $dataTable->render('category.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('category.form');
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

        Category::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-mkt-category-list')->with('success', 'Record saved successfully.');
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
        $data = Category::find($id);

        if (!$data)
        {
            return redirect()->route('app-mkt-category-list')->with('error', 'Category not found.');
        }

        return view('category.form', compact('data'));
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
        $model = Category::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    public function report()
{
    $categories = Category::with(['invoiceItems.invoice.enterprise'])->get();

    $reportData = $categories->map(function ($category) {
        $totalAmount = $category->invoiceItems->sum(function ($item) {
            return $item->quantity * $item->unit_price - ($item->discount ?? 0);
        });

        if ($totalAmount == 0) {
            return null; // Filtra categorías con total de cero
        }

        // Determina el tipo de operación (ingreso o gasto)
        $operation = $category->invoiceItems->first()->invoice->operation ?? 'Unknown';
        $labelClass = ($operation === 'Sell') ? 'bg-success' : 'bg-danger'; // Verde para ingresos, rojo para gastos

        return [
            'category' => $category->name,
            'description' => $category->description,
            'items' => $category->invoiceItems,
            'total' => $totalAmount,
            'labelClass' => $labelClass,
        ];
    })->filter(); // Elimina las categorías nulas

    return view('category.report', compact('reportData'));
}

}