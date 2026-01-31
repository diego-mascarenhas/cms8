<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use App\Services\ClaudeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TeamPromptController extends Controller
{
    protected string $promptsDir;

    public function __construct(
        protected ClaudeService $claudeService
    ) {
        $this->promptsDir = storage_path('app/claude_prompts');
    }

    /**
     * List prompts available for the team (from module_prompts, modules enabled for team).
     */
    public function list(Request $request): JsonResponse
    {
        $teamId = $request->get('team_id');
        if (! $teamId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $prompts = Prompt::query()
            ->active()
            ->whereHas('module', function ($q) use ($teamId) {
                $q->whereHas('teams', function ($t) use ($teamId) {
                    $t->where('team_id', $teamId)->where('module_team.status', 1);
                });
            })
            ->with('module:id,name,key')
            ->orderBy('order')
            ->orderBy('section_label')
            ->get(['id', 'section_key', 'section_label', 'module_id']);

        $items = $prompts->map(function (Prompt $p) {
            return [
                'id' => $p->id,
                'section_key' => $p->section_key,
                'section_label' => $p->section_label,
                'module_name' => $p->module?->name ?? '',
            ];
        });

        return response()->json([
            'success' => true,
            'prompts' => $items,
        ]);
    }

    /**
     * Invoke landing prompt: send user message and return suggestion (team token auth).
     * Accepts either prompt_id (from module_prompts) or prompt_name (file-based).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'prompt_id' => 'nullable|integer|exists:module_prompts,id',
            'prompt_name' => 'nullable|string|max:255',
            'test_message' => 'required|string|max:16000',
        ]);

        $promptId = $request->input('prompt_id');
        $promptName = $request->input('prompt_name');
        $testMessage = $request->input('test_message');
        $teamId = $request->get('team_id');

        if ($promptId !== null) {
            return $this->invokeDbPrompt((int) $promptId, $testMessage, $teamId);
        }

        $name = $promptName ?? 'default';

        // Try DB prompt by section_key (module must be enabled for team)
        $dbPrompt = Prompt::query()
            ->active()
            ->where('section_key', $name)
            ->whereHas('module', function ($q) use ($teamId) {
                $q->whereHas('teams', function ($t) use ($teamId) {
                    $t->where('team_id', $teamId)->where('module_team.status', 1);
                });
            })
            ->first();

        if ($dbPrompt !== null) {
            return $this->invokeDbPrompt($dbPrompt->id, $testMessage, $teamId);
        }

        return $this->invokeFilePrompt($name, $testMessage, $teamId);
    }

    protected function invokeDbPrompt(int $promptId, string $testMessage, $teamId): JsonResponse
    {
        $prompt = Prompt::with('module')->find($promptId);
        if (! $prompt) {
            return response()->json(['success' => false, 'message' => 'Prompt no encontrado'], 404);
        }

        $module = $prompt->module;
        if (! $module || ! $module->isActiveForTeam($teamId)) {
            return response()->json(['success' => false, 'message' => 'Prompt no disponible para tu equipo'], 403);
        }

        $userMessage = $prompt->prompt_instruction."\n\n---\n\nEntrada del usuario:\n\n".$testMessage;

        try {
            $response = $this->claudeService->chat($userMessage, [], null, $teamId);

            if (! ($response['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Error al obtener sugerencias',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'response' => $response['text'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('Team prompt invoke error', ['prompt_id' => $promptId, 'message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor. Inténtalo más tarde.',
            ], 500);
        }
    }

    protected function invokeFilePrompt(string $promptName, string $testMessage, $teamId): JsonResponse
    {
        $promptContent = null;
        if ($promptName !== 'default') {
            $safeName = str_replace(' ', '_', $promptName);
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $safeName);
            $fileName = $safeName.'.txt';
            $filePath = $this->promptsDir.DIRECTORY_SEPARATOR.$fileName;

            if (! File::exists($filePath)) {
                Log::warning('Team prompt not found', ['prompt_name' => $promptName, 'path' => $filePath]);

                return response()->json([
                    'success' => false,
                    'message' => 'Prompt no encontrado: '.$promptName,
                ], 404);
            }

            $promptContent = File::get($filePath);
        }

        try {
            $response = $this->claudeService->chat($testMessage, [], $promptContent, $teamId);

            if (! ($response['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Error al obtener sugerencias',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'response' => $response['text'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('Team prompt invoke error', ['prompt_name' => $promptName, 'message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor. Inténtalo más tarde.',
            ], 500);
        }
    }
}
