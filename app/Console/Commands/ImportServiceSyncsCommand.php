<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Models\ServiceType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportServiceSyncsCommand extends Command
{
    protected $signature = 'service-syncs:import
                            {--team_id= : Import only one team}
                            {--provider=stripe : Provider code in service_syncs}
                            {--limit=500 : Max service_syncs rows to process}
                            {--fallback-email : Resolve enterprise by email when customer_id/code does not match}
                            {--link-code-on-email-match : When fallback by email succeeds uniquely, write customer_id into enterprises.code}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map service_syncs rows into core services table (create-only idempotent by subscription_id)';

    public function handle(): int
    {
        if (! Schema::hasTable('service_syncs'))
        {
            $this->error('Table service_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $provider = strtolower(trim((string) $this->option('provider')));
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnEmailMatch = (bool) $this->option('link-code-on-email-match');

        $query = ServiceSync::query()
            ->where('provider', $provider)
            ->orderBy('team_id')
            ->orderByRaw('current_period_end IS NULL')
            ->orderBy('current_period_end')
            ->orderBy('id');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        }

        // Create-only idempotency: only sync rows not linked yet to a service.
        $query->whereNotExists(function ($q)
        {
            $q->from('services')
                ->whereColumn('services.subscription_id', 'service_syncs.id')
                ->whereNull('services.deleted_at');
        });

        $rows = $query->limit($limit)->get();

        $defaultServiceTypeId = ServiceType::query()->orderBy('id')->value('id');
        if ($defaultServiceTypeId === null)
        {
            $this->error('No service_types rows found. Create at least one service type first.');

            return self::FAILURE;
        }

        $processed = 0;
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row)
        {
            if (! $row instanceof ServiceSync)
            {
                continue;
            }

            $processed++;

            [$enterpriseId, $resolutionMode] = $this->resolveEnterpriseId(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );

            if (! $enterpriseId)
            {
                $skipped++;
                $reason = $fallbackEmail ? 'customer_id/code or unique email' : 'customer_id/code';
                $this->warn("Skip {$row->id}: enterprise not found by {$reason} for team {$row->team_id}");

                continue;
            }

            $currencyId = $this->resolveCurrencyId((string) ($row->price_currency ?? ''));
            $serviceStatus = $this->mapServiceStatus((string) ($row->status ?? ''));
            $description = trim((string) ($row->plan_name ?? ''));
            if ($description === '')
            {
                $description = 'Sync '.$provider.' '.$row->stripe_id.($resolutionMode !== 'none' ? " ({$resolutionMode})" : '');
            }

            $payload = [
                'enterprise_id' => $enterpriseId,
                'subscription_id' => $row->id,
                'service_type_id' => (int) $defaultServiceTypeId,
                'operation' => 'sell',
                'description' => $description,
                'data' => [
                    'provider' => $provider,
                    'external_id' => $row->stripe_id,
                    'customer_id' => $row->customer_id,
                    'customer_email' => $row->customer_email,
                    'resolution_mode' => $resolutionMode,
                ],
                'currency_id' => $currencyId,
                'price' => $row->amount_total ?? $row->unit_amount,
                'discount' => 0,
                'frequency' => 1,
                'next_billing' => $row->current_period_end,
                'expires_at' => $row->current_period_end,
                'responsible_id' => null,
                'status' => $serviceStatus,
            ];

            if ($dryRun)
            {
                $this->line("[dry-run] create service from sync_id={$row->id} team={$row->team_id} enterprise={$enterpriseId}");
                $created++;

                continue;
            }

            Service::withoutGlobalScopes()->create($payload);
            $created++;
        }

        $this->info(
            "Processed: {$processed} | created: {$created} | skipped: {$skipped}".
            ($dryRun ? ' | dry-run' : '')
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0:int|null,1:string}
     */
    private function resolveEnterpriseId(
        ServiceSync $row,
        bool $fallbackEmail,
        bool $linkCodeOnEmailMatch,
        bool $dryRun,
    ): array {
        $customerId = trim((string) ($row->customer_id ?? ''));
        if ($customerId !== '')
        {
            $enterprise = Enterprise::query()
                ->where('team_id', $row->team_id)
                ->where('type_id', 1)
                ->where('code', $customerId)
                ->first();

            if ($enterprise)
            {
                return [$enterprise->id, 'code'];
            }
        }

        if (! $fallbackEmail)
        {
            return [null, 'none'];
        }

        $email = strtolower(trim((string) ($row->customer_email ?? '')));
        if ($email === '')
        {
            return [null, 'none'];
        }

        $emailMatches = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->where('type_id', 1)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($emailMatches->count() !== 1)
        {
            return [null, 'none'];
        }

        /** @var Enterprise $matched */
        $matched = $emailMatches->first();

        if ($linkCodeOnEmailMatch && $customerId !== '' && blank($matched->code))
        {
            if (! $dryRun)
            {
                $matched->code = $customerId;
                $matched->save();
            }
        }

        return [$matched->id, 'email'];
    }

    private function resolveCurrencyId(string $code): ?int
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '')
        {
            return null;
        }

        return Currency::query()->where('code', $normalized)->value('id');
    }

    private function mapServiceStatus(string $providerStatus): int
    {
        return match (strtolower(trim($providerStatus)))
        {
            'active', 'trialing' => 4,
            'past_due', 'unpaid', 'incomplete' => 2,
            'canceled', 'incomplete_expired', 'paused' => 1,
            default => 1,
        };
    }
}
