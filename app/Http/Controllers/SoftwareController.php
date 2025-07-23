<?php

namespace App\Http\Controllers;

use App\DataTables\SoftwareDataTable;
use App\Models\Software;
use App\Models\Category;
use App\Models\Module;
use App\Models\Team;
use Illuminate\Http\Request;

class SoftwareController extends Controller
{
    public function __construct()
	{
		$this->authorizeResource(Software::class, 'software');
	}

    /**
     * Display a listing of the resource.
     */
    public function index(SoftwareDataTable $dataTable)
    {
        return $dataTable->render('software.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $softwareModule = Module::where('key', 'softwares')->first();
        $categories = $softwareModule ? Category::where('module_id', $softwareModule->id)->get() : collect();

        return view('software.form', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        // Add the current team ID
        $validated['team_id'] = auth()->user()->currentTeam->id;

        Software::create($validated);

        return redirect()->route('software.index')
            ->with('success', 'Software creado exitosamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Software $software)
    {
        $softwareModule = Module::where('key', 'softwares')->first();
        $categories = $softwareModule ? Category::where('module_id', $softwareModule->id)->get() : collect();

        return view('software.form', compact('software', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Software $software)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $software->update($validated);

        return redirect()->route('software.index')
            ->with('success', 'Software actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Software $software)
    {
        $software->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get software options for autocomplete.
     */
    public function autocomplete(Request $request)
    {
        $search = $request->get('q', '');

        $query = Software::with('category');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('category', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $softwares = $query->limit(15)
            ->get()
            ->map(function ($software) {
                return [
                    'id' => $software->id,
                    'text' => $software->name.($software->category ? ' ('.$software->category->name.')' : ''),
                    'name' => $software->name,
                    'category' => $software->category ? $software->category->name : '',
                ];
            });

        return response()->json([
            'results' => $softwares,
        ]);
    }
}
