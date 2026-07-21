<?php

namespace App\Http\Controllers;

use App\DataTables\AutomationDataTable;
use App\Enums\AutomationReplyType;
use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\Prompt;
use App\Services\AutomationFlowGraphSyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next)
        {
            abort_unless(auth()->user()?->currentTeam?->hasModule('automations'), 403);

            return $next($request);
        });
        $this->authorizeResource(Automation::class, 'automation', [
            'except' => ['flow', 'saveFlow'],
        ]);
    }

    public function index(AutomationDataTable $dataTable)
    {
        return $dataTable->render('automation.index');
    }

    public function create(): View
    {
        $promptOptions = $this->promptOptions();

        return view('automation.form', [
            'automation' => null,
            'promptOptions' => $promptOptions,
            'channelDefaults' => Automation::defaultChannels(),
        ]);
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $teamId = (int) auth()->user()->currentTeam->id;

        $slug = isset($validated['slug']) && trim((string) $validated['slug']) !== ''
            ? Str::slug((string) $validated['slug'])
            : Str::slug((string) $validated['name']);

        $automation = Automation::create([
            'team_id' => $teamId,
            'name' => $validated['name'],
            'slug' => $slug,
            'entry_prompt_key' => $validated['entry_prompt_key'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'channels' => Automation::normalizeChannels($validated['channels'] ?? []),
            'settings' => [
                'welcome_message' => $validated['settings']['welcome_message'] ?? null,
            ],
        ]);

        return redirect()
            ->route('automation.flow', $automation)
            ->with('success', __('Automatización creada. Diseñá el embudo conversacional.'));
    }

    public function show(Automation $automation): View
    {
        $automation->load(['steps.transitions']);

        return view('automation.show', compact('automation'));
    }

    public function edit(Automation $automation): View
    {
        $promptOptions = $this->promptOptions();
        $channelDefaults = Automation::normalizeChannels($automation->channels ?? []);

        return view('automation.form', compact('automation', 'promptOptions', 'channelDefaults'));
    }

    public function flow(Automation $automation, AutomationFlowGraphSyncer $syncer): View
    {
        $this->authorize('update', $automation);

        $graph = $syncer->export($automation);
        $promptOptions = $this->promptOptions();
        $replyTypes = collect(AutomationReplyType::cases())->map(fn (AutomationReplyType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->values();

        return view('automation.flow', compact('automation', 'graph', 'promptOptions', 'replyTypes'));
    }

    public function saveFlow(Request $request, Automation $automation, AutomationFlowGraphSyncer $syncer): JsonResponse
    {
        $this->authorize('update', $automation);

        $validated = $request->validate([
            'nodes' => ['required', 'array'],
            'nodes.*.client_id' => ['required', 'string', 'max:64'],
            'nodes.*.label' => ['required', 'string', 'max:255'],
            'nodes.*.key' => ['nullable', 'string', 'max:255'],
            'nodes.*.prompt_key' => ['nullable', 'string', 'max:255'],
            'nodes.*.instruction' => ['nullable', 'string', 'max:20000'],
            'nodes.*.is_entry' => ['sometimes', 'boolean'],
            'nodes.*.position_x' => ['nullable', 'integer'],
            'nodes.*.position_y' => ['nullable', 'integer'],
            'nodes.*.outputs' => ['nullable', 'array'],
            'nodes.*.outputs.*.id' => ['required_with:nodes.*.outputs', 'string', 'max:64'],
            'nodes.*.outputs.*.reply_type' => ['required_with:nodes.*.outputs', 'string', 'in:'.implode(',', AutomationReplyType::values())],
            'nodes.*.outputs.*.match_value' => ['nullable', 'string', 'max:255'],
            'nodes.*.outputs.*.label' => ['nullable', 'string', 'max:255'],
            'edges' => ['nullable', 'array'],
            'edges.*.from_client_id' => ['required_with:edges', 'string', 'max:64'],
            'edges.*.from_output' => ['required_with:edges', 'string', 'max:64'],
            'edges.*.to_client_id' => ['nullable', 'string', 'max:64'],
            'edges.*.reply_type' => ['nullable', 'string', 'in:'.implode(',', AutomationReplyType::values())],
            'edges.*.match_value' => ['nullable', 'string', 'max:255'],
            'edges.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $automation = $syncer->sync($automation, $validated);

        return response()->json([
            'success' => true,
            'message' => __('Embudo guardado correctamente.'),
            'graph' => $syncer->export($automation),
        ]);
    }

    public function update(UpdateAutomationRequest $request, Automation $automation): RedirectResponse
    {
        $validated = $request->validated();

        $slug = isset($validated['slug']) && trim((string) $validated['slug']) !== ''
            ? Str::slug((string) $validated['slug'])
            : Str::slug((string) $validated['name']);

        $automation->fill([
            'name' => $validated['name'],
            'slug' => $slug,
            'entry_prompt_key' => $validated['entry_prompt_key'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'channels' => Automation::normalizeChannels($validated['channels'] ?? []),
            'settings' => array_merge(
                is_array($automation->settings) ? $automation->settings : [],
                ['welcome_message' => $validated['settings']['welcome_message'] ?? null],
            ),
        ]);

        if ($request->boolean('regenerate_token'))
        {
            $automation->public_token = bin2hex(random_bytes(32));
        }

        $automation->save();

        return redirect()
            ->route('automation.show', $automation)
            ->with('success', __('Automatización actualizada correctamente.'));
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()
            ->route('automation-list')
            ->with('success', __('Automatización eliminada correctamente.'));
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    protected function promptOptions(): array
    {
        $prompts = Prompt::query()
            ->active()
            ->with('module')
            ->orderBy('order')
            ->get();

        $options = [];
        foreach ($prompts as $prompt)
        {
            $key = $prompt->module
                ? $prompt->module->key.':'.$prompt->section_key
                : $prompt->section_key;
            $options[] = [
                'key' => $key,
                'label' => $prompt->section_label.' ('.$key.')',
            ];
        }

        return $options;
    }
}
