<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RefreshServiceSyncsDataCommand extends Command
{
    protected $signature = 'service-syncs:refresh-data
                            {--team_id= : Refresh only one team}
                            {--provider=stripe : Provider code in service_syncs}
                            {--limit=1000 : Max services to process}
                            {--dry-run : Preview without writing}';

    protected $description = 'Refresh services.data from linked service_syncs.data';

    public function handle(): int
    {
        if (! Schema::hasTable('service_syncs'))
        {
            $this->error('Table service_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $provider = strtolower(trim((string) $this->option('provider')));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $rows = Service::withoutGlobalScopes()
            ->from('services')
            ->join('service_syncs', 'service_syncs.id', '=', 'services.subscription_id')
            ->whereNull('services.deleted_at')
            ->where('service_syncs.provider', $provider)
            ->when($teamId, fn ($q) => $q->where('service_syncs.team_id', $teamId))
            ->select([
                'services.id as service_id',
                'services.data as service_data',
                'service_syncs.data as sync_data',
            ])
            ->orderBy('services.id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($rows as $row)
        {
            $processed++;

            $currentData = $this->normalizeJsonValue($row->service_data);
            $newData = $this->normalizeJsonValue($row->sync_data);

            if ($currentData === $newData)
            {
                $unchanged++;
                continue;
            }

            if ($dryRun)
            {
                $this->line("[dry-run] update services.id={$row->service_id}");
                $updated++;
                continue;
            }

            Service::withoutGlobalScopes()
                ->whereKey((int) $row->service_id)
                ->update(['data' => $newData]);

            $updated++;
        }

        $this->info(
            "Processed: {$processed} | updated: {$updated} | unchanged: {$unchanged}".
            ($dryRun ? ' | dry-run' : '')
        );

        return self::SUCCESS;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeJsonValue($value): array
    {
        if (is_array($value))
        {
            return $value;
        }

        if (is_string($value) && $value !== '')
        {
            $decoded = json_decode($value, true);
            if (is_array($decoded))
            {
                return $decoded;
            }
        }

        return [];
    }
}
