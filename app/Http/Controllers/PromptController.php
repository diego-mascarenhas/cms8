<?php

namespace App\Http\Controllers;

use App\DataTables\PromptDataTable;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\TokenUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function show(Prompt $prompt)
    {
        $this->authorize('view', $prompt);
        $prompt->load('module');

        return view('prompt.show', compact('prompt'));
    }

    /**
     * Preview: call Claude API with the same request shape as AstralChartService (single user message, no system).
     */
    public function preview(Request $request, Prompt $prompt)
    {
        $this->authorize('view', $prompt);
        $request->validate([
            'test_message' => 'required|string|max:16000',
        ]);

        $userMessage = $prompt->prompt_instruction."\n\n---\n\nEntrada del usuario:\n\n".$request->test_message;

        $payload = [
            'model' => config('anthropic.model', 'claude-sonnet-4-5-20250929'),
            'max_tokens' => (int) config('anthropic.max_tokens', 2000),
            'temperature' => (float) config('anthropic.temperature', 0.7),
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        \Log::info('Prompt Preview Request', [
            'model' => $payload['model'],
            'max_tokens' => $payload['max_tokens'],
            'temperature' => $payload['temperature'],
            'api_url' => config('anthropic.api_url'),
        ]);

        $baseUrl = rtrim(config('anthropic.api_url', 'https://api.anthropic.com/v1'), '/');
        $response = Http::withHeaders([
            'x-api-key' => config('anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('anthropic.timeout', 30))
            ->post("{$baseUrl}/messages", $payload);

        if (! $response->successful())
        {
            \Log::error('Prompt Preview Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error communicating with Claude API: '.$response->status(),
                'details' => $response->json(),
            ]);
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';

        // Log token usage from API response
        $usage = $data['usage'] ?? [];
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $totalTokens = $inputTokens + $outputTokens;

        try
        {
            TokenUsageLog::create([
                'team_id' => auth()->user()->currentTeam->id,
                'module_id' => $prompt->module_id ?? TokenUsageLog::inferModuleId(),
                'service' => 'PromptController',
                'json_size' => strlen($userMessage),
                'toon_size' => 0,
                'json_tokens' => $totalTokens,
                'toon_tokens' => 0,
                'savings_percentage' => 0,
                'used_toon' => false,
            ]);
        } catch (\Exception $e)
        {
            \Log::error('Failed to log token usage', [
                'error' => $e->getMessage(),
                'prompt_id' => $prompt->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'response' => $text ?: '',
        ]);
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
