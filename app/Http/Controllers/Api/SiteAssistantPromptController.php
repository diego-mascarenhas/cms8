<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSiteAssistantPromptContentRequest;
use App\Services\AssistantPromptCatalog;
use App\Services\TeamSiteAssistantPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SiteAssistantPromptController extends Controller
{
    public function show(Request $request, TeamSiteAssistantPromptService $siteAssistant): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('update', $team);

        return response()->json([
            'success' => true,
            'data' => $siteAssistant->settingsPayload($team),
        ]);
    }

    public function update(Request $request, TeamSiteAssistantPromptService $siteAssistant): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('update', $team);

        $validated = $request->validate([
            'prompt_key' => ['nullable', 'string', 'max:255'],
        ]);

        try
        {
            $siteAssistant->select($team, $validated['prompt_key'] ?? null);
        } catch (InvalidArgumentException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('team_settings.site_assistant.saved'),
            'data' => $siteAssistant->settingsPayload($team->fresh()),
        ]);
    }

    public function updateContent(UpdateSiteAssistantPromptContentRequest $request, TeamSiteAssistantPromptService $siteAssistant): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $validated = $request->validated();

        try
        {
            $siteAssistant->updateContent(
                $team,
                $validated['prompt_key'],
                $validated['section_label'],
                $validated['prompt_instruction'],
            );
        } catch (InvalidArgumentException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('team_settings.site_assistant.updated'),
            'data' => $siteAssistant->settingsPayload($team->fresh()),
        ]);
    }

    public function store(Request $request, TeamSiteAssistantPromptService $siteAssistant): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('update', $team);

        $validated = $request->validate([
            'section_label' => ['required', 'string', 'max:255'],
            'prompt_instruction' => ['required', 'string'],
        ], [
            'section_label.required' => __('team_settings.site_assistant.label_required'),
            'prompt_instruction.required' => __('team_settings.site_assistant.instruction_required'),
        ]);

        try
        {
            $siteAssistant->create(
                $team,
                $validated['section_label'],
                $validated['prompt_instruction'],
            );
        } catch (InvalidArgumentException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('team_settings.site_assistant.created'),
            'data' => $siteAssistant->settingsPayload($team->fresh()),
        ], 201);
    }

    public function applyCatalog(Request $request, TeamSiteAssistantPromptService $siteAssistant, AssistantPromptCatalog $catalog): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('update', $team);

        $validated = $request->validate([
            'prompt_key' => ['required', 'string', 'max:255'],
        ]);

        try
        {
            $key = $catalog->apply($team, $validated['prompt_key']);
            $siteAssistant->select($team->fresh(), $key);
        } catch (InvalidArgumentException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('team_settings.site_assistant.saved'),
            'data' => $siteAssistant->settingsPayload($team->fresh()),
        ]);
    }
}
