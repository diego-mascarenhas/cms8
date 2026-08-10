<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Project;
use App\Models\Team;
use App\Services\ProjectBudgetSpecService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenerateProjectFunnelQuoteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(
        public int $projectId,
        public int $teamId,
        public int $contactId,
        public string $brief,
    ) {}

    public function handle(ProjectBudgetSpecService $budgetSpecService): void
    {
        $project = Project::withoutGlobalScopes()
            ->where('team_id', $this->teamId)
            ->where('id', $this->projectId)
            ->first();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $this->teamId)
            ->where('id', $this->contactId)
            ->first();

        $team = Team::query()->find($this->teamId);

        if (! $project || ! $contact || ! $team)
        {
            return;
        }

        $this->markFunnelStatus($project, 'processing');

        try
        {
            $spec = $budgetSpecService->generate($this->brief, $team);
        } catch (RuntimeException $e)
        {
            Log::error('GenerateProjectFunnelQuoteJob AI failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);
            $this->markFunnelStatus($project, 'failed', $e->getMessage());

            return;
        } catch (\Throwable $e)
        {
            Log::error('GenerateProjectFunnelQuoteJob failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);
            $this->markFunnelStatus($project, 'failed', __('Could not generate the estimate. Please try again.'));

            return;
        }

        $data = (array) ($project->data ?? []);
        $funnel = is_array($data['funnel'] ?? null) ? $data['funnel'] : [];

        $project->fill([
            'description' => (string) ($spec['ai_interpretation'] ?? $this->brief),
            'data' => array_merge($data, [
                'budget_given' => $this->brief,
                'ai_interpretation' => $spec['ai_interpretation'] ?? '',
                'dimension' => $spec['dimension'] ?? '',
                'estimated_times' => $spec['estimated_times'] ?? '',
                'resources' => $spec['resources'] ?? '',
                'token_consumption' => is_array($spec['token_consumption'] ?? null)
                    ? $spec['token_consumption']
                    : $budgetSpecService->buildTokenConsumption(
                        is_array($spec['suggested_tasks'] ?? null) ? $spec['suggested_tasks'] : [],
                    ),
                'suggested_tasks' => is_array($spec['suggested_tasks'] ?? null) ? $spec['suggested_tasks'] : [],
                'funnel_spec' => $spec,
                'funnel' => array_merge($funnel, [
                    'source' => 'projects_funnel',
                    'contact_id' => $contact->id,
                    'quoted_at' => now()->toIso8601String(),
                    'quote_status' => 'ready',
                    'quote_error' => null,
                ]),
            ]),
        ])->save();
    }

    private function markFunnelStatus(Project $project, string $status, ?string $error = null): void
    {
        $data = (array) ($project->data ?? []);
        $funnel = is_array($data['funnel'] ?? null) ? $data['funnel'] : [];
        $data['funnel'] = array_merge($funnel, [
            'quote_status' => $status,
            'quote_error' => $error,
        ]);
        $project->forceFill(['data' => $data])->save();
    }
}
