<?php

namespace App\Http\Controllers;

use App\DataTables\PromptDataTable;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\TokenUsageLog;
use App\Support\AiTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;

use function Laravel\Ai\agent;

class PromptController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next)
        {
            abort_unless(auth()->user()?->currentTeam?->hasModule('prompts'), 403);

            return $next($request);
        });
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
        $validated['team_id'] = auth()->user()->currentTeam->id;

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
     * Preview: use Laravel AI SDK with optional image/audio input and optional TTS response.
     */
    public function preview(Request $request, Prompt $prompt)
    {
        try
        {
            $this->authorize('view', $prompt);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e)
        {
            return response()->json(['success' => false, 'message' => __('No tienes permiso para esta acción.')], 403);
        }

        try
        {
            $request->validate([
                'test_message' => 'nullable|string|max:16000',
                'image' => 'nullable|image|max:20480',
                'audio' => 'nullable|file|mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg|max:25600',
                'respond_with_audio' => 'nullable|boolean',
                'translate_to' => 'nullable|string|in:es,en,fr,de,it,pt,ja,zh,ru,ar',
                'voice_id' => 'nullable|string|max:100',
            ], [
                'image.image' => __('El archivo debe ser una imagen.'),
                'image.max' => __('La imagen no puede superar 20 MB.'),
                'audio.mimes' => __('El audio debe ser mp3, wav, m4a, webm u ogg.'),
                'audio.max' => __('El audio no puede superar 25 MB.'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e)
        {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
            ], 422);
        }

        try
        {
            return $this->runPreview($request, $prompt);
        } catch (\Throwable $e)
        {
            return $this->previewFallback($e);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    private function runPreview(Request $request, Prompt $prompt)
    {
        $hasText = filled($request->input('test_message'));
        $hasImage = $request->hasFile('image');
        $hasAudio = $request->hasFile('audio');

        if (! $hasText && ! $hasImage && ! $hasAudio)
        {
            return response()->json([
                'success' => false,
                'message' => __('Indica un texto, sube una imagen o un audio para probar el prompt.'),
            ], 422);
        }

        $userContent = $request->input('test_message', '');
        if ($hasAudio)
        {
            try
            {
                $transcript = (string) Transcription::fromUpload($request->file('audio'))
                    ->generate(provider: Lab::OpenAI);
                $userContent = trim($userContent."\n\n[Transcripción del audio]:\n".$transcript);
            } catch (\Throwable $e)
            {
                Log::warning('Prompt preview transcription failed', ['error' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'message' => __('No se pudo transcribir el audio. Comprueba que OPENAI_API_KEY esté configurada.'),
                ], 422);
            }
        }

        $translateTo = $request->input('translate_to');
        $languageInstruction = '';
        if ($translateTo)
        {
            $languageNames = [
                'es' => 'Español',
                'en' => 'English',
                'fr' => 'Français',
                'de' => 'Deutsch',
                'it' => 'Italiano',
                'pt' => 'Português',
                'ja' => '日本語',
                'zh' => '中文',
                'ru' => 'Русский',
                'ar' => 'العربية',
            ];
            $langName = $languageNames[$translateTo] ?? $translateTo;
            $languageInstruction = "\n\n**Importante:** Responde únicamente en {$langName}. Si el usuario ha subido audio, traduce o resume el contenido en {$langName}.";
        }

        $routedTo = null;
        if ($prompt->isGeneralRouter())
        {
            $prompt = $this->resolveGeneralPromptRoute($prompt, $userContent);
            if ($prompt === null)
            {
                return response()->json([
                    'success' => false,
                    'message' => __('No se pudo determinar el flujo. Intenta ser más específico.'),
                ], 422);
            }
            $routedTo = $prompt->section_label;
        }

        $userMessage = $prompt->prompt_instruction.$languageInstruction."\n\n---\n\nEntrada del usuario:\n\n".$userContent;
        $attachments = $hasImage ? [$request->file('image')] : [];

        try
        {
            $agent = agent(
                instructions: $prompt->prompt_instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userMessage, $attachments, AiTasks::provider('assistant'));
            $text = $response->text ?: '';
        } catch (\Throwable $e)
        {
            Log::error('Prompt preview failed', ['error' => $e->getMessage(), 'prompt_id' => $prompt->id]);

            return response()->json([
                'success' => false,
                'message' => __('Error al comunicar con la IA: ').$e->getMessage(),
            ]);
        }

        $usage = $response->usage;
        $totalTokens = $usage->promptTokens + $usage->completionTokens;
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
            Log::error('Failed to log token usage', ['error' => $e->getMessage(), 'prompt_id' => $prompt->id]);
        }

        $payload = [
            'success' => true,
            'response' => $text,
        ];
        if ($routedTo !== null)
        {
            $payload['routed_to'] = $routedTo;
        }

        $respondWithAudio = $request->boolean('respond_with_audio');
        $voiceId = $request->input('voice_id') ? trim($request->input('voice_id')) : null;
        if ($respondWithAudio && $text !== '' && config('ai.providers.eleven.key'))
        {
            $maxCharsForTts = 1000;
            $textForTts = strlen($text) > $maxCharsForTts
                ? substr($text, 0, $maxCharsForTts).'…'
                : $text;
            try
            {
                $pendingAudio = Audio::of($textForTts);
                if ($voiceId !== null && $voiceId !== '')
                {
                    $pendingAudio = $pendingAudio->voice($voiceId);
                }
                $audioResponse = $pendingAudio->generate(provider: Lab::ElevenLabs);
                $payload['audio_base64'] = $audioResponse->audio;
                $payload['audio_mime'] = $audioResponse->mimeType() ?? 'audio/mpeg';
            } catch (\Throwable $e)
            {
                Log::warning('Prompt preview TTS failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json($payload);
    }

    /**
     * When the prompt is the general router, call the agent to get a routing key and return the target prompt.
     */
    private function resolveGeneralPromptRoute(Prompt $routerPrompt, string $userContent): ?Prompt
    {
        $routerMessage = $routerPrompt->prompt_instruction."\n\n---\n\nEntrada del usuario:\n\n".$userContent;

        try
        {
            $agent = agent(
                instructions: $routerPrompt->prompt_instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($routerMessage, [], AiTasks::provider('assistant'));
            $text = trim($response->text ?: '');
        } catch (\Throwable $e)
        {
            Log::warning('General router call failed', ['error' => $e->getMessage()]);

            return Prompt::findByRoutingKey('contacts:landing');
        }

        $firstLine = trim(explode("\n", $text)[0] ?? '');
        $firstLine = preg_replace('/^[\s`*#\-]+|[\s`*]+$/u', '', $firstLine);
        if (preg_match('/[a-z0-9_]+:[a-z0-9_]+/u', $firstLine, $m))
        {
            $key = $m[0];
        } else
        {
            $key = trim($firstLine);
        }

        $target = Prompt::findByRoutingKey($key);

        return $target ?? Prompt::findByRoutingKey('contacts:landing');
    }

    /**
     * Ensure preview never returns HTML on unexpected errors.
     */
    protected function previewFallback(\Throwable $e): \Illuminate\Http\JsonResponse
    {
        Log::error('Prompt preview unexpected error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

        return response()->json([
            'success' => false,
            'message' => __('Error inesperado. Comprueba los logs o inténtalo más tarde.'),
        ], 500);
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
