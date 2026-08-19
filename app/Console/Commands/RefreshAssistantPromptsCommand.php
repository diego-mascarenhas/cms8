<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Models\Team;
use App\Services\DefaultAssistantFlowPromptsService;
use Database\Seeders\PromptSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Replay the shipped prompt defaults onto a team that was seeded before the copy was rewritten.
 *
 * Prompts live in `module_prompts` per team, so editing the defaults in code only reaches teams
 * created afterwards. This command overwrites the copy of an existing team on demand.
 */
class RefreshAssistantPromptsCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'assistant:refresh-prompts
                            {team : Team ID whose prompts should be reset to the shipped defaults}
                            {--create-missing : Also add module prompts this team never had (except our own-brand sales scripts), instead of only rewriting the ones it already uses}
                            {--dry-run : List what would change without writing anything}
                            {--force : Skip the confirmation prompt in production}';

    protected $description = 'Overwrite a team\'s assistant prompts (module_prompts) with the current shipped defaults';

    public function handle(): int
    {
        $teamId = (int) $this->argument('team');

        $team = Team::withoutGlobalScopes()->find($teamId);
        if ($team === null)
        {
            $this->error("No team with id {$teamId}.");

            return self::FAILURE;
        }

        $createMissing = (bool) $this->option('create-missing');

        $this->line("Team #{$team->id} — {$team->name}");

        if ($this->option('dry-run'))
        {
            $this->table(
                ['Routing key', 'Current length', 'Default length'],
                $this->plannedChanges($teamId, $createMissing),
            );
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed('This overwrites any prompt this team edited by hand.'))
        {
            return self::FAILURE;
        }

        $modulePrompts = $this->refreshModulePrompts($teamId, $createMissing);
        $flowPrompts = DefaultAssistantFlowPromptsService::refreshForTeam($teamId);

        $this->info("Module prompts rewritten: {$modulePrompts}");
        $this->info("Assistant flow prompts rewritten: {$flowPrompts}");

        return self::SUCCESS;
    }

    /**
     * Apply the PromptSeeder definitions to this team.
     *
     * Only rewrites rows the team already has: a team seeded without the module prompts should
     * not suddenly grow 19 entries in /prompt/list. Pass --create-missing to add them.
     */
    private function refreshModulePrompts(int $teamId, bool $createMissing): int
    {
        $touched = 0;

        foreach ((new PromptSeeder)->getPromptDefinitions() as $definition)
        {
            $existing = $this->existingModulePrompt($teamId, $definition);

            if ($existing === null && ! $this->shouldCreate($definition, $createMissing))
            {
                continue;
            }

            $data = PromptSeeder::promptAttributes($definition);
            $data['team_id'] = $teamId;

            Prompt::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $teamId,
                    'module_id' => $data['module_id'],
                    'section_key' => $data['section_key'],
                ],
                $data,
            );
            $touched++;
        }

        return $touched;
    }

    /**
     * Own-brand sales scripts are never pushed into a team that does not already have them.
     *
     * @param  array<string, mixed>  $definition
     */
    private function shouldCreate(array $definition, bool $createMissing): bool
    {
        return $createMissing && ! ($definition['own_brand'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function existingModulePrompt(int $teamId, array $data): ?Prompt
    {
        return Prompt::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('module_id', $data['module_id'])
            ->where('section_key', $data['section_key'])
            ->first();
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function plannedChanges(int $teamId, bool $createMissing): array
    {
        $rows = [];

        foreach ((new PromptSeeder)->getPromptDefinitions() as $data)
        {
            $existing = $this->existingModulePrompt($teamId, $data);

            if ($existing === null && ! $this->shouldCreate($data, $createMissing))
            {
                $rows[] = [(string) $data['section_key'], 'absent', 'skipped'];

                continue;
            }

            $rows[] = [
                (string) $data['section_key'],
                $existing === null ? 'new' : (string) mb_strlen((string) $existing->prompt_instruction),
                (string) mb_strlen((string) $data['prompt_instruction']),
            ];
        }

        foreach (DefaultAssistantFlowPromptsService::definitions() as $def)
        {
            $current = Prompt::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('section_key', $def['section_key'])
                ->value('prompt_instruction');

            $rows[] = [
                $def['module_key'].':'.$def['section_key'],
                $current === null ? 'new' : (string) mb_strlen((string) $current),
                (string) mb_strlen($def['prompt_instruction']),
            ];
        }

        return $rows;
    }
}
