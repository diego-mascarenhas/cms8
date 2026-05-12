<?php

namespace App\Http\Controllers;

use App\DataTables\TemplateDataTable;
use App\Helpers\GrapesJsHelper;
use App\Http\Requests\DuplicateTemplateRequest;
use App\Jobs\GenerateTemplateHtmlJob;
use App\Models\Message;
use App\Models\Template;
use App\Services\TemplateHtmlGenerationService;
use App\Support\TemplateEditorReturnUrl;
use Dotlogics\Grapesjs\App\Editor\Config;
use Dotlogics\Grapesjs\App\Traits\EditorTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    use EditorTrait;

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
     * If creating (no id) and ai_prompt is provided, generates HTML via AI and redirects to editor.
     */
    public function store(Request $request, TemplateHtmlGenerationService $htmlService): RedirectResponse
    {
        $data = $request->except(['id', '_token', 'ai_prompt']);

        $request->validate([
            'name' => 'required|string|min:3|max:75',
            'ai_prompt' => 'nullable|string|max:2000',
        ]);

        $status_id = $request->has('status_id') ? 1 : 0;
        $isCreate = ! $request->filled('id');
        $aiPrompt = $request->input('ai_prompt');
        $gjsData = $data['gjs_data'] ?? null;

        if ($isCreate && $aiPrompt && trim($aiPrompt) !== '')
        {
            $result = $htmlService->generate(trim($aiPrompt), auth()->user()->currentTeam);
            if ($result['success'] && ! empty($result['html']))
            {
                $template = Template::create([
                    'name' => $data['name'],
                    'status_id' => $status_id,
                    'gjs_data' => ['html' => $result['html'], 'css' => ''],
                ]);
                GrapesJsHelper::fixTemplateStructure($template);

                return redirect()
                    ->route('template.editor', $template->getHashedId())
                    ->with('success', __('Template created. Edit and save in the visual editor.'));
            }

            $errorMessage = $result['error'] ?? __('Unknown error');
            \Illuminate\Support\Facades\Log::warning('Template AI generation failed on create', [
                'prompt_length' => strlen(trim($aiPrompt)),
                'error' => $errorMessage,
            ]);

            $template = Template::create([
                'name' => $data['name'],
                'status_id' => $status_id,
            ]);

            return redirect()
                ->route('template.index')
                ->with('warning', __('Template created. AI generation failed: :error. Open the editor and use "Generate with AI" to try again.', ['error' => $errorMessage]));
        }

        $payload = [
            'name' => $data['name'],
            'status_id' => $status_id,
        ];
        if ($gjsData !== null)
        {
            $payload['gjs_data'] = $gjsData;
        }

        $template = Template::updateOrCreate(
            ['id' => $request->id],
            $payload,
        );

        return redirect()->route('template.index')->with('success', __('Record saved successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $hashedId)
    {
        $page = Template::findByHash($hashedId);

        if (! $page)
        {
            return redirect()->route('template.index')->with('error', 'Template not found.');
        }

        return view('template.show', compact('page'));
    }

    /**
     * Public view of a template (no auth). Used for shared links from assistant/WhatsApp.
     */
    public function showPublic(string $hashedId)
    {
        $page = Template::findByHash($hashedId);

        if (! $page)
        {
            abort(404, 'Template not found.');
        }

        return view('template.show', compact('page'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $hashedId)
    {
        $data = Template::findByHash($hashedId);

        if (! $data)
        {
            return redirect()->route('template.index')->with('error', 'Template not found.');
        }

        return view('template.form', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $hashedId)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $hashedId)
    {
        $model = Template::findByHash($hashedId);

        if (! $model)
        {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }

    public function editor(Request $request, string $hashedId): View|RedirectResponse
    {
        $page = Template::findByHash($hashedId);

        if (! $page)
        {
            return redirect()->route('template.index')->with('error', 'Template not found.');
        }

        $teamId = auth()->user()->currentTeam->id ?? 'default';
        $request->merge(['team_id' => $teamId]);

        $returnUrl = TemplateEditorReturnUrl::validatedFromRequest($request);
        $editorConfig = app(Config::class)->initialize($page);

        return view('laravel-grapesjs::edittor', [
            'editorConfig' => $editorConfig,
            'model' => $page,
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * Duplicate a template (same team) and redirect to the visual editor for the copy.
     */
    public function duplicate(DuplicateTemplateRequest $request, string $hashedId): RedirectResponse
    {
        $source = Template::findByHash($hashedId);

        if (! $source)
        {
            return redirect()->back()->with('error', __('Template not found.'));
        }

        $validated = $request->validated();
        $newName = Str::limit(trim((string) $validated['duplicate_template_name']), 75, '');

        $gjsData = $source->gjs_data;
        if (! is_array($gjsData))
        {
            $gjsData = [];
        } else
        {
            $gjsData = json_decode(json_encode($gjsData), true) ?? [];
        }

        $copy = Template::create([
            'name' => $newName,
            'status_id' => false,
            'gjs_data' => $gjsData,
        ]);

        $linkedToMessage = false;
        $messageId = isset($validated['message_id']) ? (int) $validated['message_id'] : 0;

        if ($messageId > 0)
        {
            $message = Message::query()->whereKey($messageId)->first();

            if ($message !== null && (int) $message->type_id === 1)
            {
                $message->template_id = $copy->id;
                $message->save();
                $linkedToMessage = true;
            }
        }

        $successKey = $linkedToMessage
            ? 'app.email_template_duplicate_success_linked'
            : 'app.email_template_duplicate_success';

        return redirect()
            ->route('template.editor', $copy->getHashedId())
            ->with('success', __($successKey));
    }

    /**
     * Dispatch a job to generate email template HTML from a natural language prompt.
     * Returns 202 with a token; client should poll generateHtmlResult(token) until completed/failed.
     */
    public function generateHtml(Request $request): JsonResponse
    {
        try
        {
            $request->validate([
                'prompt' => 'required|string|max:2000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e)
        {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        }

        $token = Str::random(64);
        $team = auth()->user()->currentTeam;
        $teamId = $team?->id;

        Cache::put(GenerateTemplateHtmlJob::cacheKey($token), ['status' => 'pending'], GenerateTemplateHtmlJob::CACHE_TTL_SECONDS);

        GenerateTemplateHtmlJob::dispatch(
            $request->input('prompt'),
            $teamId,
            $token,
        );

        return response()->json(['token' => $token], 202);
    }

    /**
     * Return the result of an async template HTML generation (polled by the editor).
     */
    public function generateHtmlResult(string $token): JsonResponse
    {
        $result = GenerateTemplateHtmlJob::getResult($token);

        if ($result === null)
        {
            return response()->json(['error' => 'Unknown or expired token.'], 404);
        }

        return response()->json($result);
    }
}
