<?php

namespace App\Http\Controllers;

use App\DataTables\AutomationDataTable;
use App\Enums\AutomationKind;
use App\Enums\AutomationReplyType;
use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\Prompt;
use App\Services\AutomationFlowGraphSyncer;
use App\Support\TeamModuleAccess;
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
            TeamModuleAccess::abortUnless('automations');

            return $next($request);
        });
        $this->authorizeResource(Automation::class, 'automation', [
            'except' => ['flow', 'saveFlow', 'showFunnel', 'indexFunnels', 'indexActions', 'createFunnel', 'createAction'],
        ]);
    }

    public function indexFunnels(AutomationDataTable $dataTable)
    {
        $this->authorize('viewAny', Automation::class);

        return $dataTable->forKind(AutomationKind::Funnel)
            ->render('automation.index', [
                'kind' => AutomationKind::Funnel,
            ]);
    }

    public function indexActions(AutomationDataTable $dataTable)
    {
        $this->authorize('viewAny', Automation::class);

        return $dataTable->forKind(AutomationKind::Action)
            ->render('automation.index', [
                'kind' => AutomationKind::Action,
            ]);
    }

    public function createFunnel(): View
    {
        $this->authorize('create', Automation::class);

        return $this->createForm(AutomationKind::Funnel);
    }

    public function createAction(): View
    {
        $this->authorize('create', Automation::class);

        return $this->createForm(AutomationKind::Action);
    }

    public function create(): View
    {
        return $this->createAction();
    }

    public function index(AutomationDataTable $dataTable)
    {
        return $this->indexActions($dataTable);
    }

    protected function createForm(AutomationKind $kind): View
    {
        return view('automation.form', [
            'automation' => null,
            'kind' => $kind,
            'promptOptions' => $this->promptOptions(),
            'channelDefaults' => Automation::defaultChannels(),
            'actionAutomations' => collect(),
        ]);
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $teamId = (int) auth()->user()->currentTeam->id;
        $kind = AutomationKind::tryFrom((string) ($validated['kind'] ?? '')) ?? AutomationKind::Action;

        $slug = isset($validated['slug']) && trim((string) $validated['slug']) !== ''
            ? Str::slug((string) $validated['slug'])
            : Str::slug((string) $validated['name']);

        $automation = Automation::create([
            'team_id' => $teamId,
            'name' => $validated['name'],
            'slug' => $slug,
            'kind' => $kind,
            'entry_prompt_key' => $validated['entry_prompt_key'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'channels' => Automation::normalizeChannels($validated['channels'] ?? []),
            'settings' => [
                'welcome_message' => $validated['settings']['welcome_message'] ?? null,
            ],
        ]);

        if ($kind === AutomationKind::Funnel)
        {
            return redirect()
                ->route('funnel.flow', $automation)
                ->with('success', __('Embudo creado. Diseñá el flujo conversacional.'));
        }

        return redirect()
            ->route('automation.show', $automation)
            ->with('success', __('Automatización creada correctamente.'));
    }

    public function show(Automation $automation): View
    {
        abort_if($automation->isFunnel(), 404);

        $automation->load(['steps.transitions.toAutomation']);

        return view('automation.show', compact('automation'));
    }

    /**
     * Read-only overview of a funnel: steps and each reply selection / exit.
     */
    public function showFunnel(Automation $automation): View
    {
        $this->authorize('view', $automation);
        abort_unless($automation->isFunnel(), 404);

        $automation->load([
            'steps' => fn ($q) => $q->orderByDesc('is_entry')->orderBy('position_y')->orderBy('position_x'),
            'steps.transitions.toStep',
            'steps.transitions.toAutomation',
        ]);

        return view('automation.funnel-show', compact('automation'));
    }

    public function edit(Automation $automation): View
    {
        $this->assertRouteMatchesKind($automation);

        $promptOptions = $this->promptOptions();
        $channelDefaults = Automation::normalizeChannels($automation->channels ?? []);

        return view('automation.form', [
            'automation' => $automation,
            'kind' => $automation->kind ?? AutomationKind::Action,
            'promptOptions' => $promptOptions,
            'channelDefaults' => $channelDefaults,
            'actionAutomations' => collect(),
        ]);
    }

    public function flow(Automation $automation, AutomationFlowGraphSyncer $syncer): View
    {
        $this->authorize('update', $automation);
        abort_unless($automation->isFunnel(), 404);

        $graph = $syncer->export($automation);
        $promptOptions = $this->promptOptions();
        $replyTypes = collect(AutomationReplyType::cases())->map(fn (AutomationReplyType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->values();
        $actionAutomations = Automation::query()
            ->actions()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'entry_prompt_key'])
            ->map(fn (Automation $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
            ])
            ->values()
            ->all();

        return view('automation.flow', compact('automation', 'graph', 'promptOptions', 'replyTypes', 'actionAutomations'));
    }

    public function saveFlow(Request $request, Automation $automation, AutomationFlowGraphSyncer $syncer): JsonResponse
    {
        $this->authorize('update', $automation);
        abort_unless($automation->isFunnel(), 404);

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
            'nodes.*.outputs.*.to_automation_id' => ['nullable', 'integer'],
            'edges' => ['nullable', 'array'],
            'edges.*.from_client_id' => ['required_with:edges', 'string', 'max:64'],
            'edges.*.from_output' => ['required_with:edges', 'string', 'max:64'],
            'edges.*.to_client_id' => ['nullable', 'string', 'max:64'],
            'edges.*.to_automation_id' => ['nullable', 'integer'],
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
        $this->assertRouteMatchesKind($automation);

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

        $kind = $automation->kind ?? AutomationKind::Action;

        return redirect()
            ->route($kind->showRouteName(), $automation)
            ->with('success', __('Actualizado correctamente.'));
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $this->assertRouteMatchesKind($automation);

        $listRoute = ($automation->kind ?? AutomationKind::Action)->listRouteName();
        $automation->delete();

        return redirect()
            ->route($listRoute)
            ->with('success', __('Eliminado correctamente.'));
    }

    /**
     * Keep funnel.* and automation.* URLs aligned with the model kind.
     */
    protected function assertRouteMatchesKind(Automation $automation): void
    {
        $routeName = (string) request()->route()?->getName();
        $isFunnelRoute = str_starts_with($routeName, 'funnel.');
        $isAutomationRoute = str_starts_with($routeName, 'automation.');

        if ($isFunnelRoute)
        {
            abort_unless($automation->isFunnel(), 404);
        }

        if ($isAutomationRoute)
        {
            abort_unless($automation->isAction(), 404);
        }
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
