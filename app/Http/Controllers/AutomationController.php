<?php

namespace App\Http\Controllers;

use App\DataTables\AutomationDataTable;
use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
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
        $this->authorizeResource(Automation::class, 'automation');
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
            ->route('automation.show', $automation)
            ->with('success', __('Automatización creada correctamente.'));
    }

    public function show(Automation $automation): View
    {
        return view('automation.show', compact('automation'));
    }

    public function edit(Automation $automation): View
    {
        $promptOptions = $this->promptOptions();
        $channelDefaults = Automation::normalizeChannels($automation->channels ?? []);

        return view('automation.form', compact('automation', 'promptOptions', 'channelDefaults'));
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
