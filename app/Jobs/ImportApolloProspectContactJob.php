<?php

namespace App\Jobs;

use App\Models\ProspectImport;
use App\Models\Team;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Prospecting\ProspectContactImporter;
use App\Support\HumanoPricingCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportApolloProspectContactJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $person
     */
    public function __construct(
        public int $teamId,
        public int $userId,
        public string $apolloId,
        public array $person,
        public ?int $categoryId = null,
        public ?int $searchId = null,
        public ?int $importId = null,
    ) {
        $this->onQueue('prospects');
    }

    public function handle(ProspectContactImporter $importer, AssistantSubscriptionService $access): void
    {
        $team = Team::query()->find($this->teamId);
        if ($team === null)
        {
            return;
        }

        $catalog = $access->accessForCatalog($team, HumanoPricingCatalog::MAILER);
        if (($catalog['status'] ?? null) !== 'paid')
        {
            return;
        }

        $person = $this->person;

        try
        {
            $importer->import(
                $team,
                $this->userId,
                $this->apolloId,
                trim((string) ($person['first_name'] ?? '')) ?: 'Contact',
                isset($person['last_name']) ? (string) $person['last_name'] : null,
                isset($person['last_name_obfuscated']) ? (string) $person['last_name_obfuscated'] : null,
                isset($person['title']) ? (string) $person['title'] : null,
                isset($person['organization_name']) ? (string) $person['organization_name'] : null,
                is_array($person['apollo_raw'] ?? null) ? $person['apollo_raw'] : $person,
                $this->categoryId,
                $this->searchId,
                $this->importId,
            );
            $this->record($importer->lastOutcome, $importer->lastCredits);
        } catch (Throwable $exception)
        {
            $this->record('failed', 0);
            Log::error('Apollo prospect contact import failed', [
                'team_id' => $team->id,
                'apollo_id' => $this->apolloId,
                'category_id' => $this->categoryId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function record(string $outcome, int $credits): void
    {
        if ($this->importId === null)
        {
            return;
        }

        ProspectImport::query()->find($this->importId)?->noteContact($outcome, $credits);
    }
}
