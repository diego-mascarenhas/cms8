<?php

namespace App\Services\Billing;

use App\Enums\TeamBillingFrequency;
use App\Models\Team;
use App\Models\TeamUsageInvoice;
use App\Models\TeamUsageInvoiceAdjustment;
use App\Services\TeamBillingUsageSummaryService;
use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeamUsageInvoiceDraftIssuer
{
    public function __construct(
        private readonly TeamBillingUsageSummaryService $usage,
        private readonly TeamUsageInvoiceStripeGateway $stripe,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function issueDueDrafts(?Team $onlyTeam = null, bool $dryRun = false, ?Carbon $now = null): Collection
    {
        $now = ($now ?? now())->copy();
        $results = collect();

        $teams = $onlyTeam
            ? collect([$onlyTeam])
            : Team::query()->whereNotNull('stripe_id')->where('stripe_id', '!=', '')->get();

        foreach ($teams as $team)
        {
            foreach ($this->dueJobs($team, $now) as $job)
            {
                $results->push($dryRun ? $this->previewJob($team, $job) : $this->issueJob($team, $job));
            }
        }

        return $results;
    }

    /**
     * @return list<array{kind: string, frequency: TeamBillingFrequency, from: Carbon, closes_on: Carbon, adjustment: ?TeamUsageInvoiceAdjustment}>
     */
    public function dueJobs(Team $team, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();
        $frequency = TeamUsageInvoiceFrequency::for($team);
        $since = $frequency === TeamBillingFrequency::Weekly
            ? $now->copy()->subWeeks(2)
            : $now->copy()->subMonthsNoOverflow(2);
        $jobs = [];

        foreach (TeamUsageInvoiceFrequency::closedWindows($team, $now, $since) as $window)
        {
            if ($this->alreadyIssued($team, $window['from'], $window['closes_on']))
            {
                continue;
            }

            $jobs[] = [
                'kind' => TeamUsageInvoice::KIND_CYCLE,
                'frequency' => $window['frequency'],
                'from' => $window['from'],
                'closes_on' => $window['closes_on'],
                'adjustment' => null,
            ];
        }

        $adjustments = TeamUsageInvoiceAdjustment::query()
            ->pending()
            ->where('team_id', $team->id)
            ->orderBy('period_from')
            ->get();

        foreach ($adjustments as $adjustment)
        {
            if ($this->alreadyIssued($team, $adjustment->period_from, $adjustment->period_to))
            {
                continue;
            }

            $jobs[] = [
                'kind' => TeamUsageInvoice::KIND_ADJUSTMENT,
                'frequency' => $adjustment->frequency,
                'from' => $adjustment->period_from->copy(),
                'closes_on' => $adjustment->period_to->copy(),
                'adjustment' => $adjustment,
            ];
        }

        return $jobs;
    }

    /**
     * @param  array{kind: string, frequency: TeamBillingFrequency, from: Carbon, closes_on: Carbon, adjustment: ?TeamUsageInvoiceAdjustment}  $job
     * @return array<string, mixed>
     */
    private function previewJob(Team $team, array $job): array
    {
        $usage = $this->usage->forClosedWindow($team, $job['from'], $job['closes_on'], $job['frequency']);
        $lines = $this->usage->billableLines($usage, $usage['period_label']);

        return [
            'team_id' => $team->id,
            'kind' => $job['kind'],
            'period_from' => $job['from']->toDateString(),
            'period_to' => $job['closes_on']->toDateString(),
            'billed_cents' => $usage['billed_cents'],
            'lines' => count($lines),
            'status' => $usage['billed_cents'] <= 0 || $lines === [] ? 'skipped' : 'dry-run',
            'stripe_invoice_id' => null,
        ];
    }

    /**
     * @param  array{kind: string, frequency: TeamBillingFrequency, from: Carbon, closes_on: Carbon, adjustment: ?TeamUsageInvoiceAdjustment}  $job
     * @return array<string, mixed>
     */
    private function issueJob(Team $team, array $job): array
    {
        $usage = $this->usage->forClosedWindow($team, $job['from'], $job['closes_on'], $job['frequency']);
        $lines = $this->usage->billableLines($usage, $usage['period_label']);

        if ($usage['billed_cents'] <= 0 || $lines === [])
        {
            return [
                'team_id' => $team->id,
                'kind' => $job['kind'],
                'period_from' => $job['from']->toDateString(),
                'period_to' => $job['closes_on']->toDateString(),
                'billed_cents' => $usage['billed_cents'],
                'lines' => 0,
                'status' => 'skipped',
                'stripe_invoice_id' => null,
            ];
        }

        if (! $team->stripe_id)
        {
            return [
                'team_id' => $team->id,
                'kind' => $job['kind'],
                'period_from' => $job['from']->toDateString(),
                'period_to' => $job['closes_on']->toDateString(),
                'billed_cents' => $usage['billed_cents'],
                'lines' => count($lines),
                'status' => 'no-customer',
                'stripe_invoice_id' => null,
            ];
        }

        $stripeInvoiceId = null;

        try
        {
            $stripeInvoice = $this->stripe->createDraftInvoice($team, $usage['currency'], [
                'humano_usage' => '1',
                'humano_team_id' => (string) $team->id,
                'humano_kind' => $job['kind'],
                'humano_frequency' => $job['frequency']->value,
                'humano_period_from' => $job['from']->toIso8601String(),
                'humano_period_to' => $job['closes_on']->toIso8601String(),
            ]);
            $stripeInvoiceId = (string) $stripeInvoice->id;

            foreach ($lines as $line)
            {
                $description = $line['description'];
                if (filled($line['detail'] ?? null))
                {
                    $description .= ' · '.$line['detail'];
                }

                $this->stripe->addInvoiceItem(
                    (string) $team->stripe_id,
                    $stripeInvoiceId,
                    $description,
                    $line['amount_cents'],
                    $usage['currency'],
                );
            }

            $record = TeamUsageInvoice::query()->create([
                'team_id' => $team->id,
                'kind' => $job['kind'],
                'frequency' => $job['frequency'],
                'period_from' => $job['from'],
                'period_to' => $job['closes_on'],
                'billed_cents' => $usage['billed_cents'],
                'currency' => $usage['currency'],
                'stripe_invoice_id' => $stripeInvoiceId,
                'status' => TeamUsageInvoice::STATUS_DRAFT,
                'adjustment_id' => $job['adjustment']?->id,
                'issued_at' => now(),
            ]);

            if ($job['adjustment'])
            {
                $job['adjustment']->forceFill(['invoiced_at' => now()])->save();
            }

            return [
                'team_id' => $team->id,
                'kind' => $job['kind'],
                'period_from' => $job['from']->toDateString(),
                'period_to' => $job['closes_on']->toDateString(),
                'billed_cents' => $usage['billed_cents'],
                'lines' => count($lines),
                'status' => TeamUsageInvoice::STATUS_DRAFT,
                'stripe_invoice_id' => $record->stripe_invoice_id,
            ];
        } catch (Throwable $e)
        {
            if ($stripeInvoiceId !== null)
            {
                try
                {
                    $this->stripe->deleteDraftInvoice($stripeInvoiceId);
                } catch (Throwable $cleanupError)
                {
                    Log::warning('Failed to discard orphan usage invoice draft', [
                        'team_id' => $team->id,
                        'stripe_invoice_id' => $stripeInvoiceId,
                        'message' => $cleanupError->getMessage(),
                    ]);
                }
            }

            Log::error('Failed to create usage invoice draft', [
                'team_id' => $team->id,
                'period_from' => $job['from']->toIso8601String(),
                'period_to' => $job['closes_on']->toIso8601String(),
                'stripe_invoice_id' => $stripeInvoiceId,
                'message' => $e->getMessage(),
            ]);

            return [
                'team_id' => $team->id,
                'kind' => $job['kind'],
                'period_from' => $job['from']->toDateString(),
                'period_to' => $job['closes_on']->toDateString(),
                'billed_cents' => $usage['billed_cents'],
                'lines' => count($lines),
                'status' => 'error',
                'stripe_invoice_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function alreadyIssued(Team $team, Carbon $from, Carbon $closesOn): bool
    {
        return TeamUsageInvoice::query()
            ->where('team_id', $team->id)
            ->where('period_from', $from)
            ->where('period_to', $closesOn)
            ->exists();
    }
}
