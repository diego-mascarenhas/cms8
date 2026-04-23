<?php

namespace App\Console\Commands;

use App\Models\InvoiceSync;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Stripe\StripeClient;

class SyncStripeInvoicesCommand extends Command
{
    protected $signature = 'stripe:sync-invoices
                            {--team_id= : Sync only one team}
                            {--mode=backfill : backfill (cursor-based) or mutable (refresh invoices that can still change)}
                            {--limit=500 : Maximum invoices to sync per team in this run}
                            {--starting-after= : Stripe invoice id cursor to continue from previous block}
                            {--no-resume : Ignore saved team cursor and start without checkpoint}
                            {--recent-days=45 : In mutable mode, look back this many days when --from/--to are not set}
                            {--from= : Invoice created date from (Y-m-d)}
                            {--to= : Invoice created date to (Y-m-d)}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill Stripe invoices into invoice_syncs in blocks';

    public function handle(): int
    {
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $mode = strtolower(trim((string) ($this->option('mode') ?? 'backfill')));
        if (! in_array($mode, ['backfill', 'mutable'], true))
        {
            $this->error("Invalid --mode={$mode}. Allowed: backfill, mutable");

            return self::INVALID;
        }

        $maxPerTeam = max(1, (int) $this->option('limit'));
        $startingAfterOption = trim((string) ($this->option('starting-after') ?? ''));
        $noResume = (bool) $this->option('no-resume');
        $recentDays = max(1, (int) $this->option('recent-days'));
        $dryRun = (bool) $this->option('dry-run');

        $createdFilter = $this->buildCreatedFilter(
            $this->option('from'),
            $this->option('to'),
        );
        if ($mode === 'mutable' && $createdFilter === [])
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

        $globalSynced = 0;
        $globalScanned = 0;
        $processedTeams = 0;

        foreach ($teams as $team)
        {
            $secret = trim((string) $team->getSetting('stripe_secret'));
            if ($secret === '')
            {
                $this->line("Skipping team {$team->id} ({$team->name}): missing stripe_secret in team settings.");
                continue;
            }

            $client = new StripeClient($secret);
            $syncedForTeam = 0;
            $scannedForTeam = 0;
            $lastProcessedId = null;

            if ($mode === 'mutable')
            {
                [$syncedForTeam, $scannedForTeam] = $this->syncMutableInvoicesForTeam(
                    $client,
                    $team->id,
                    $maxPerTeam,
                    $createdFilter,
                    $dryRun,
                );
            } else
            {
                $savedCursor = trim((string) $team->getSetting('stripe_invoices_sync_cursor', ''));
                $startingAfter = $startingAfterOption;
                if ($startingAfter === '' && ! $noResume)
                {
                    $startingAfter = $savedCursor;
                }

                [$syncedForTeam, $scannedForTeam, $lastProcessedId] = $this->syncBackfillInvoicesForTeam(
                    $client,
                    $team->id,
                    $maxPerTeam,
                    $createdFilter,
                    $startingAfter,
                    $dryRun,
                );
            }

            $processedTeams++;
            $globalSynced += $syncedForTeam;
            $globalScanned += $scannedForTeam;

            $dryText = $dryRun ? ' [dry-run]' : '';
            $this->info("Team {$team->id} ({$team->name}): synced {$syncedForTeam}/{$maxPerTeam} invoices{$dryText}.");
            if ($mode === 'backfill' && $lastProcessedId)
            {
                $this->line("Team {$team->id}: next cursor --starting-after={$lastProcessedId}");
            }

            if ($mode === 'mutable')
            {
                $this->line("Team {$team->id}: mutable refresh done (statuses: draft, open, uncollectible).");
            } else
            {
                if ($syncedForTeam >= $maxPerTeam)
                {
                    if (! $dryRun)
                    {
                        $team->setSetting('stripe_invoices_sync_cursor', (string) $lastProcessedId, [
                            'type' => 'string',
                            'group' => 'stripe',
                        ]);
                        $team->setSetting('stripe_invoices_sync_cursor_updated_at', now()->toDateTimeString(), [
                            'type' => 'string',
                            'group' => 'stripe',
                        ]);
                    }
                    $this->line("Team {$team->id}: limit reached, run command again to continue with next block.");
                } else
                {
                    if (! $dryRun)
                    {
                        // Completed all available pages for current filters.
                        $team->setSetting('stripe_invoices_sync_cursor', '', [
                            'type' => 'string',
                            'group' => 'stripe',
                        ]);
                        $team->setSetting('stripe_invoices_sync_cursor_updated_at', now()->toDateTimeString(), [
                            'type' => 'string',
                            'group' => 'stripe',
                        ]);
                    }
                    $this->line("Team {$team->id}: reached end of available invoices for current filter.");
                }
            }
        }

        if ($processedTeams === 0)
        {
            $this->warn('No team with stripe_secret found. Configure team Stripe secret key first.');

            return self::SUCCESS;
        }

        $drySuffix = $dryRun ? ' [dry-run]' : '';
        $this->info("Stripe invoices sync complete: teams={$processedTeams}, synced={$globalSynced}, scanned={$globalScanned}{$drySuffix}.");

        return self::SUCCESS;
    }

    /**
     * @param  array{gte?: int, lte?: int}  $createdFilter
     * @return array{0: int, 1: int, 2: string|null}
     */
    private function syncBackfillInvoicesForTeam(
        StripeClient $client,
        int $teamId,
        int $maxPerTeam,
        array $createdFilter,
        string $startingAfter,
        bool $dryRun,
    ): array {
        $params = [
            'limit' => 100,
            'expand' => [
                'data.customer',
                'data.subscription',
            ],
        ];

        if ($createdFilter !== [])
        {
            $params['created'] = $createdFilter;
        }
        if ($startingAfter !== '')
        {
            $params['starting_after'] = $startingAfter;
            $this->line("Team {$teamId}: resuming with cursor {$startingAfter}");
        }

        $synced = 0;
        $scanned = 0;
        $lastProcessedId = null;

        $collection = $client->invoices->all($params);
        foreach ($collection->autoPagingIterator() as $invoice)
        {
            if ($synced >= $maxPerTeam)
            {
                break;
            }

            $scanned++;
            $payload = $invoice->toArray();
            $lastProcessedId = (string) Arr::get($payload, 'id');

            if (! $dryRun)
            {
                $this->upsertInvoiceSyncRow($teamId, $payload);
            }

            $synced++;
        }

        return [$synced, $scanned, $lastProcessedId];
    }

    /**
     * @param  array{gte?: int, lte?: int}  $createdFilter
     * @return array{0: int, 1: int}
     */
    private function syncMutableInvoicesForTeam(
        StripeClient $client,
        int $teamId,
        int $maxPerTeam,
        array $createdFilter,
        bool $dryRun,
    ): array {
        $statuses = ['draft', 'open', 'uncollectible'];
        $synced = 0;
        $scanned = 0;

        foreach ($statuses as $status)
        {
            if ($synced >= $maxPerTeam)
            {
                break;
            }

            $remaining = $maxPerTeam - $synced;
            $params = [
                'limit' => min(100, $remaining),
                'status' => $status,
                'expand' => [
                    'data.customer',
                    'data.subscription',
                ],
            ];

            if ($createdFilter !== [])
            {
                $params['created'] = $createdFilter;
            }

            $collection = $client->invoices->all($params);
            foreach ($collection->autoPagingIterator() as $invoice)
            {
                if ($synced >= $maxPerTeam)
                {
                    break 2;
                }

                $scanned++;
                $payload = $invoice->toArray();
                if (! $dryRun)
                {
                    $this->upsertInvoiceSyncRow($teamId, $payload);
                }
                $synced++;
            }
        }

        return [$synced, $scanned];
    }

    /**
     * @return array{gte?: int, lte?: int}
     */
    private function buildCreatedFilter(?string $from, ?string $to): array
    {
        $filter = [];

        if (is_string($from) && trim($from) !== '')
        {
            try
            {
                $fromTs = Carbon::parse($from)->startOfDay()->timestamp;
                $filter['gte'] = $fromTs;
            } catch (\Throwable)
            {
                $this->warn("Ignoring invalid --from value: {$from}");
            }
        }

        if (is_string($to) && trim($to) !== '')
        {
            try
            {
                $toTs = Carbon::parse($to)->endOfDay()->timestamp;
                $filter['lte'] = $toTs;
            } catch (\Throwable)
            {
                $this->warn("Ignoring invalid --to value: {$to}");
            }
        }

        return $filter;
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    private function upsertInvoiceSyncRow(int $teamId, array $invoicePayload): void
    {
        $externalId = trim((string) Arr::get($invoicePayload, 'id'));
        if ($externalId === '')
        {
            return;
        }

        $customerData = [];
        $customerField = Arr::get($invoicePayload, 'customer');
        if (is_array($customerField))
        {
            $customerData = $customerField;
        }

        $customerId = is_string($customerField)
            ? $customerField
            : Arr::get($customerData, 'id');

        $subscriptionField = Arr::get($invoicePayload, 'subscription');
        $subscriptionId = is_string($subscriptionField)
            ? $subscriptionField
            : Arr::get($subscriptionField, 'id');

        $discountLabels = [];
        $discounts = Arr::get($invoicePayload, 'discounts', []);
        if (is_array($discounts))
        {
            foreach ($discounts as $discount)
            {
                $name = Arr::get($discount, 'coupon.name')
                    ?? Arr::get($discount, 'coupon.id')
                    ?? Arr::get($discount, 'promotion_code.code');

                if (filled($name))
                {
                    $discountLabels[] = (string) $name;
                }
            }
        }

        InvoiceSync::updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'stripe',
                'external_id' => $externalId,
            ],
            [
                'stripe_subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'customer_email' => Arr::get($invoicePayload, 'customer_email')
                    ?? Arr::get($invoicePayload, 'customer_details.email')
                    ?? Arr::get($customerData, 'email'),
                'customer_name' => Arr::get($invoicePayload, 'customer_name')
                    ?? Arr::get($invoicePayload, 'customer_details.name')
                    ?? Arr::get($customerData, 'name'),
                'customer_description' => Arr::get($customerData, 'description'),
                'customer_tax_id' => Arr::get($invoicePayload, 'customer_tax_ids.0.value')
                    ?? Arr::get($invoicePayload, 'customer_details.tax_ids.0.value'),
                'customer_address_country' => strtoupper((string) (Arr::get($invoicePayload, 'customer_address.country')
                    ?? Arr::get($invoicePayload, 'customer_details.address.country')
                    ?? Arr::get($customerData, 'address.country'))) ?: null,
                'number' => Arr::get($invoicePayload, 'number'),
                'status' => Arr::get($invoicePayload, 'status'),
                'billing_reason' => Arr::get($invoicePayload, 'billing_reason'),
                'closed' => (bool) Arr::get($invoicePayload, 'closed', false),
                'currency' => strtolower((string) Arr::get($invoicePayload, 'currency', 'usd')),
                'amount_due' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'amount_due_decimal'),
                    Arr::get($invoicePayload, 'amount_due'),
                ),
                'amount_paid' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'amount_paid_decimal'),
                    Arr::get($invoicePayload, 'amount_paid'),
                ),
                'amount_remaining' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'amount_remaining_decimal'),
                    Arr::get($invoicePayload, 'amount_remaining'),
                ),
                'subtotal' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'subtotal_excluding_tax_decimal')
                    ?? Arr::get($invoicePayload, 'subtotal_decimal'),
                    Arr::get($invoicePayload, 'subtotal_excluding_tax')
                    ?? Arr::get($invoicePayload, 'subtotal'),
                ),
                'tax' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'tax_decimal'),
                    Arr::get($invoicePayload, 'tax'),
                ),
                'total' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'total_decimal'),
                    Arr::get($invoicePayload, 'total'),
                ),
                'total_discount_amount' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'total_discount_amounts.0.amount_excluding_tax_decimal')
                    ?? Arr::get($invoicePayload, 'total_discount_amounts.0.amount_decimal'),
                    Arr::get($invoicePayload, 'total_discount_amounts.0.amount_excluding_tax')
                    ?? Arr::get($invoicePayload, 'total_discount_amounts.0.amount'),
                ),
                'applied_coupons' => $discountLabels === [] ? null : implode(', ', $discountLabels),
                'invoice_created_at' => $this->normalizeTimestamp(Arr::get($invoicePayload, 'created')),
                'invoice_due_date' => $this->normalizeTimestamp(Arr::get($invoicePayload, 'due_date')),
                'paid' => (bool) Arr::get($invoicePayload, 'paid', false),
                'hosted_invoice_url' => Arr::get($invoicePayload, 'hosted_invoice_url'),
                'invoice_pdf' => Arr::get($invoicePayload, 'invoice_pdf'),
                'last_synced_at' => now(),
                'raw_payload' => $invoicePayload,
            ],
        );
    }

    private function normalizeAmount(?string $decimalAmount, mixed $integerAmount): ?float
    {
        if ($decimalAmount !== null)
        {
            return (float) $decimalAmount;
        }

        if (is_numeric($integerAmount))
        {
            return ((float) $integerAmount) / 100;
        }

        return null;
    }

    private function normalizeTimestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value))
        {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $value)->setTimezone(config('app.timezone'));
    }
}
