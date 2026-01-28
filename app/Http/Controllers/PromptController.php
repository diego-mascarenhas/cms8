<?php

namespace App\Http\Controllers;

use App\DataTables\PromptDataTable;
use App\Models\Module;
use App\Models\Prompt;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Prompt::class, 'prompt');
    }

    public function index(PromptDataTable $dataTable)
    {
        return $dataTable->render('prompt.index');
    }

    public function create()
    {
        $modules = Module::orderBy('name')->get();

        return view('prompt.form', compact('modules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'section_key' => 'required|string|max:255',
            'section_label' => 'required|string|max:255',
            'prompt_instruction' => 'required|string',
            'helper_text' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ], [
            'module_id.required' => 'El módulo es obligatorio.',
            'section_key.required' => 'La clave de sección es obligatoria.',
            'section_label.required' => 'La etiqueta de sección es obligatoria.',
            'prompt_instruction.required' => 'La instrucción para la IA es obligatoria.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['order'] = (int) ($validated['order'] ?? 0);

        Prompt::create($validated);

        return redirect()->route('prompt-list')->with('success', __('Prompt creado correctamente.'));
    }

    public function edit(Prompt $prompt)
    {
        $modules = Module::orderBy('name')->get();

        return view('prompt.form', compact('prompt', 'modules'));
    }

    public function update(Request $request, Prompt $prompt)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'section_key' => 'required|string|max:255',
            'section_label' => 'required|string|max:255',
            'prompt_instruction' => 'required|string',
            'helper_text' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['order'] = (int) ($validated['order'] ?? 0);

        $prompt->update($validated);

        return redirect()->route('prompt-list')->with('success', __('Prompt actualizado correctamente.'));
    }

    public function destroy(Prompt $prompt)
    {
        $prompt->delete();

        return redirect()->route('prompt-list')->with('success', __('Prompt eliminado correctamente.'));
    }
}
