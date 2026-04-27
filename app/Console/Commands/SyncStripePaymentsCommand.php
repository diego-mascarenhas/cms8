<?php

namespace App\Console\Commands;

use App\Models\PaymentSync;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Stripe\StripeClient;

class SyncStripePaymentsCommand extends Command
{
    protected $signature = 'stripe:sync-payments
                            {--team_id= : Sync only one team}
                            {--limit=100 : Maximum charges to upsert into payment_syncs per team in this run}
                            {--recent-days=90 : When --from/--to are not set, only charges created on or after this many days ago}
                            {--from= : Charge created from (Y-m-d)}
                            {--to= : Charge created to (Y-m-d)}
                            {--dry-run : Preview without writing}';

    protected $description = 'Sync Stripe charges into payment_syncs (staging; run payment-syncs:import-stripe to build payments)';

    public function handle(): int
    {
        if (! Schema::hasTable('payment_syncs'))
        {
            $this->error('Table payment_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $maxPerTeam = max(1, (int) $this->option('limit'));
        $recentDays = max(1, (int) $this->option('recent-days'));
        $dryRun = (bool) $this->option('dry-run');

        $createdFilter = $this->buildCreatedFilter(
            $this->option('from'),
            $this->option('to'),
        );
        if ($createdFilter === [])
        {
            $createdFilter['gte'] = now()->subDays($recentDays)->startOfDay()->timestamp;
        }

        $teams = Team::query()->with('settings');
        if ($teamId)
        {
            $teams->whereKey($teamId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
        $teams = $teams->get();
        if ($teams->isEmpty())
        {
            $this->warn('No teams found for synchronization.');

            return self::SUCCESS;
        }

        $globalUpserted = 0;
        $globalSkipped = 0;
        $globalScanned = 0;

        foreach ($teams as $team)
        {
            $secret = trim((string) $team->getSetting('stripe_secret'));
            if ($secret === '')
            {
                $this->line("Skipping team {$team->id} ({$team->name}): missing stripe_secret in team settings.");

                continue;
            }

            $client = new StripeClient($secret);

            $params = [
                'limit' => min(100, $maxPerTeam),
                'expand' => ['data.customer'],
                'created' => $createdFilter,
            ];

            $pageStartingAfter = '';
            $hasMore = true;
            $upserted = 0;
            $skipped = 0;
            $scanned = 0;

            while ($upserted < $maxPerTeam && $hasMore)
            {
                if ($pageStartingAfter !== '')
                {
                    $params['starting_after'] = $pageStartingAfter;
                } else
                {
                    unset($params['starting_after']);
                }

                $page = $client->charges->all($params)->toArray();
                $raw = is_array($page) ? ($page['data'] ?? []) : [];
                $hasMore = (bool) ($page['has_more'] ?? false);

                if (! is_array($raw) || $raw === [])
                {
                    break;
                }

                $rawLastCursor = $this->lastStripeListItemId($raw);
                $rows = array_values(array_filter($raw, 'is_array'));

                foreach ($rows as $row)
                {
                    if ($upserted >= $maxPerTeam)
                    {
                        break 2;
                    }

                    $scanned++;
                    $chargeId = (string) ($row['id'] ?? '');
                    if ($chargeId === '' || ! str_starts_with($chargeId, 'ch_'))
                    {
                        $skipped++;

                        continue;
                    }

                    $status = strtolower((string) ($row['status'] ?? ''));
                    $amountCents = (int) ($row['amount'] ?? 0);
                    $refundedCents = (int) ($row['amount_refunded'] ?? 0);
                    $netCents = $amountCents - $refundedCents;

                    $currency = strtoupper((string) ($row['currency'] ?? 'usd'));

                    $customerId = null;
                    $customerEmail = null;
                    $customer = $row['customer'] ?? null;
                    if (is_string($customer))
                    {
                        $customerId = $customer;
                    } elseif (is_array($customer))
                    {
                        $customerId = isset($customer['id']) ? (string) $customer['id'] : null;
                        $customerEmail = isset($customer['email']) ? strtolower(trim((string) $customer['email'])) : null;
                    }

                    $invoiceExternalId = null;
                    $inv = $row['invoice'] ?? null;
                    if (is_string($inv) && str_starts_with($inv, 'in_'))
                    {
                        $invoiceExternalId = $inv;
                    } elseif (is_array($inv) && isset($inv['id']))
                    {
                        $iid = (string) $inv['id'];
                        if (str_starts_with($iid, 'in_'))
                        {
                            $invoiceExternalId = $iid;
                        }
                    }

                    $createdTs = (int) ($row['created'] ?? 0);
                    $chargeCreatedAt = $createdTs > 0
                        ? Carbon::createFromTimestamp($createdTs)
                        : null;

                    $description = $row['description'] ?? null;
                    if (is_string($description) && $description === '')
                    {
                        $description = null;
                    }

                    if ($dryRun)
                    {
                        $this->line("[dry-run] team={$team->id} charge={$chargeId} status={$status} net_cents={$netCents}");
                        $upserted++;

                        continue;
                    }

                    PaymentSync::query()->updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'provider' => 'stripe',
                            'external_id' => $chargeId,
                        ],
                        [
                            'customer_id' => $customerId,
                            'customer_email' => $customerEmail,
                            'status' => $status,
                            'currency' => $currency,
                            'amount_cents' => $amountCents,
                            'amount_refunded_cents' => $refundedCents,
                            'amount_net_cents' => $netCents,
                            'invoice_external_id' => $invoiceExternalId,
                            'description' => is_string($description) ? $description : null,
                            'charge_created_at' => $chargeCreatedAt,
                            'last_synced_at' => now(),
                            'raw_payload' => $row,
                        ],
                    );
                    $upserted++;
                }

                if (! $hasMore)
                {
                    break;
                }
                if ($rawLastCursor === null)
                {
                    $this->warn("Team {$team->id}: could not read Stripe pagination cursor; stopping.");
                    break;
                }
                $pageStartingAfter = $rawLastCursor;
            }

            $globalUpserted += $upserted;
            $globalSkipped += $skipped;
            $globalScanned += $scanned;

            $this->info("Team {$team->id} ({$team->name}): payment_syncs upserted {$upserted}, skipped {$skipped}, scanned {$scanned}".($dryRun ? ' [dry-run]' : '').'.');
        }

        $this->line('');
        $this->info("Done. Teams: {$teams->count()} | payment_syncs upserts: {$globalUpserted} | skipped: {$globalSkipped} | scanned: {$globalScanned}".($dryRun ? ' | dry-run' : ''));
        $this->line('Run payment-syncs:import-stripe to create payments for the finance dashboard.');

        return self::SUCCESS;
    }

    /**
     * @return array{gte?: int, lte?: int}
     */
    private function buildCreatedFilter(mixed $from, mixed $to): array
    {
        $filter = [];

        if (is_string($from) && trim($from) !== '')
        {
            try
            {
                $filter['gte'] = Carbon::parse($from)->startOfDay()->timestamp;
            } catch (\Throwable)
            {
            }
        }

        if (is_string($to) && trim($to) !== '')
        {
            try
            {
                $filter['lte'] = Carbon::parse($to)->endOfDay()->timestamp;
            } catch (\Throwable)
            {
            }
        }

        return $filter;
    }

    private function lastStripeListItemId(array $items): ?string
    {
        if ($items === [])
        {
            return null;
        }
        $last = $items[count($items) - 1];

        return is_array($last) ? (isset($last['id']) ? (string) $last['id'] : null) : null;
    }
}
