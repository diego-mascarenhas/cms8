<?php

namespace App\Observers;

use App\Models\Team;
use App\Services\DefaultAssistantFlowPromptsService;
use Database\Seeders\PromptSeeder;

/**
 * Give every new team its prompt library.
 *
 * This hangs off the model rather than the TeamCreated event because teams are also created
 * outside Jetstream's action: self-registration, the payment-link signup flow and AuthController
 * all build the team directly, and none of them dispatch that event.
 *
 * Both calls use firstOrCreate, so a team that already has a prompt keeps its own copy.
 */
class TeamDefaultPromptsObserver
{
    public function created(Team $team): void
    {
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        PromptSeeder::seedForTeam($team);
    }
}
