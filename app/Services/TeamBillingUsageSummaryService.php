<?php

namespace App\Services;

use App\Enums\TeamBillingFrequency;
use App\Helpers\Helpers;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Services\Billing\ClientTokenPresenter;
use App\Support\MailerPaygPricing;
use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class TeamBillingUsageSummaryService
{
    public function __construct(
        private readonly ClientTokenPresenter $tokens,
    ) {}

    /**
     * @return array{
     *     month: string,
     *     month_label: string,
     *     period_label: string,
     *     from: string,
     *     to: string,
     *     is_current: bool,
     *     multiplier: float,
     *     tokens_real: int,
     *     tokens_billed: int,
     *     token_cost_cents: int,
     *     token_billed_cents: int,
     *     token_markup_cents: int,
     *     whatsapp_messages: int,
     *     whatsapp_billed_cents: int,
     *     mailer_emails: int,
     *     mailer_overage: int,
     *     mailer_billed_cents: int,
     *     cost_cents: int,
     *     billed_cents: int,
     *     markup_cents: int,
     *     currency: string,
     *     formatted: array<string, string>
     * }
     */
    public function forMonth(Team $team, ?Carbon $month = null): array
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $isCurrent = $from->isSameMonth(now());
        $to = $isCurrent ? now() : $month->copy()->endOfMonth();

        return $this->forPeriod($team, $from, $to, $isCurrent, TeamBillingFrequency::Monthly);
    }

    /**
     * @return array{
     *     month: string,
     *     month_label: string,
     *     period_label: string,
     *     from: string,
     *     to: string,
     *     is_current: bool,
     *     multiplier: float,
     *     tokens_real: int,
     *     tokens_billed: int,
     *     token_cost_cents: int,
     *     token_billed_cents: int,
     *     token_markup_cents: int,
     *     whatsapp_messages: int,
     *     whatsapp_billed_cents: int,
     *     mailer_emails: int,
     *     mailer_overage: int,
     *     mailer_billed_cents: int,
     *     cost_cents: int,
     *     billed_cents: int,
     *     markup_cents: int,
     *     currency: string,
     *     frequency: string,
     *     frequency_label: string,
     *     closes_on: string,
     *     formatted: array<string, string>
     * }
     */
    public function invoicePreview(Team $team, ?TeamBillingFrequency $frequency = null): array
    {
        $frequency ??= TeamUsageInvoiceFrequency::for($team);
        [$from, $closesOn] = $this->openPeriod($frequency);
        $to = now()->lt($closesOn) ? now() : $closesOn->copy();
        $usage = $this->forPeriod($team, $from, $to, true, $frequency);

        return array_merge($usage, [
            'frequency' => $frequency->value,
            'frequency_label' => $frequency->label(),
            'closes_on' => $closesOn->format('d/m/Y'),
            'period_label' => $this->periodLabel($from, $closesOn, $frequency),
        ]);
    }

    /**
     * @return array{
     *     month: string,
     *     month_label: string,
     *     from: string,
     *     to: string,
     *     is_current: bool,
     *     multiplier: float,
     *     tokens_real: int,
     *     tokens_billed: int,
     *     token_cost_cents: int,
     *     token_billed_cents: int,
     *     token_markup_cents: int,
     *     whatsapp_messages: int,
     *     whatsapp_billed_cents: int,
     *     mailer_emails: int,
     *     mailer_overage: int,
     *     mailer_billed_cents: int,
     *     cost_cents: int,
     *     billed_cents: int,
     *     markup_cents: int,
     *     currency: string,
     *     formatted: array<string, string>
     * }
     */
    public function currentMonth(Team $team): array
    {
        return $this->forMonth($team, now());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pastMonths(Team $team, int $months = 12): Collection
    {
        $rows = collect();

        for ($offset = 1; $offset <= $months; $offset++)
        {
            $row = $this->forMonth($team, now()->copy()->subMonths($offset));
            if ($this->monthHasConsumption($row))
            {
                $rows->push($row);
            }
        }

        return $rows;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function openPeriod(TeamBillingFrequency $frequency, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();

        if ($frequency === TeamBillingFrequency::Weekly)
        {
            return [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ];
        }

        return [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forPeriod(
        Team $team,
        Carbon $from,
        Carbon $to,
        bool $isCurrent,
        TeamBillingFrequency $frequency,
    ): array {
        $asOf = $isCurrent ? $to->copy() : $from->copy();

        $this->tokens->usingTeam($team);
        $stats = TeamApiUsageStatsService::forTeam((int) $team->id, $from, $to);
        $realTokens = (int) $stats['totalTokensUsed'];
        $billedTokens = $this->tokens->scale($realTokens, $asOf);
        $tokenCostCents = $this->tokens->costCents($realTokens, 0, null, $asOf);
        $tokenBilledCents = $this->tokens->costCents($billedTokens, 0, null, $asOf);
        $tokenMarkupCents = max(0, $tokenBilledCents - $tokenCostCents);

        $whatsapp = TeamWhatsAppUsageStatsService::forTeam($team, $from, $to);
        $mailer = $this->mailerForPeriod($team, $from, $to, $asOf);
        $currency = TokenBillingRateService::displayCurrency();
        $multiplier = TokenBillingRateService::clientTokenMultiplier($team, $asOf);

        $costCents = $tokenCostCents;
        $billedCents = $tokenBilledCents + (int) $whatsapp['our_amount_cents'] + $mailer['billed_cents'];
        $markupCents = $tokenMarkupCents;
        $periodLabel = $this->periodLabel($from, $to, $frequency);

        return [
            'month' => $from->format('Y-m'),
            'month_label' => $this->monthLabel($from),
            'period_label' => $periodLabel,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'is_current' => $isCurrent,
            'multiplier' => $multiplier,
            'tokens_real' => $realTokens,
            'tokens_billed' => $billedTokens,
            'token_cost_cents' => $tokenCostCents,
            'token_billed_cents' => $tokenBilledCents,
            'token_markup_cents' => $tokenMarkupCents,
            'whatsapp_messages' => (int) $whatsapp['messages_sent'],
            'whatsapp_billed_cents' => (int) $whatsapp['our_amount_cents'],
            'mailer_emails' => $mailer['emails'],
            'mailer_overage' => $mailer['overage'],
            'mailer_billed_cents' => $mailer['billed_cents'],
            'cost_cents' => $costCents,
            'billed_cents' => $billedCents,
            'markup_cents' => $markupCents,
            'currency' => $currency,
            'formatted' => [
                'tokens' => $this->formatCount($realTokens).' → '.$this->formatCount($billedTokens),
                'tokens_real' => $this->formatCount($realTokens),
                'tokens_billed' => $this->formatCount($billedTokens),
                'multiplier' => TeamBillingRate::formatAmount($multiplier),
                'whatsapp' => $this->formatCount((int) $whatsapp['messages_sent']).' / '.$this->formatCents((int) $whatsapp['our_amount_cents'], $currency),
                'mailer' => $this->formatCount($mailer['overage']).' / '.$this->formatCents($mailer['billed_cents'], $currency),
                'cost' => $this->formatCents($costCents, $currency),
                'billed' => $this->formatCents($billedCents, $currency),
                'markup' => $this->formatCents($markupCents, $currency),
                'token_cost' => $this->formatCents($tokenCostCents, $currency),
                'token_billed' => $this->formatCents($tokenBilledCents, $currency),
                'token_markup' => $this->formatCents($tokenMarkupCents, $currency),
                'whatsapp_billed' => $this->formatCents((int) $whatsapp['our_amount_cents'], $currency),
                'mailer_billed' => $this->formatCents($mailer['billed_cents'], $currency),
            ],
        ];
    }

    /**
     * @return array{emails: int, overage: int, billed_cents: int}
     */
    private function mailerForPeriod(Team $team, Carbon $from, Carbon $to, Carbon $asOf): array
    {
        $emails = $team->messageDeliveries()
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', $to)
            ->where('sent_at', '>=', $from)
            ->count();

        $limit = (int) $team->getSetting('email_monthly_limit', $team->getEmailPlan()->getMonthlyLimit());
        $overage = $team->allowsMailerOverage()
            ? max(0, $emails - $limit)
            : 0;

        return [
            'emails' => $emails,
            'overage' => $overage,
            'billed_cents' => MailerPaygPricing::overageDueCents($overage, $team, $asOf),
        ];
    }

    /**
     * @param  array{tokens_real: int, whatsapp_messages: int, mailer_emails: int, billed_cents: int}  $row
     */
    private function monthHasConsumption(array $row): bool
    {
        return $row['tokens_real'] > 0
            || $row['whatsapp_messages'] > 0
            || $row['mailer_emails'] > 0
            || $row['billed_cents'] > 0;
    }

    private function periodLabel(Carbon $from, Carbon $to, TeamBillingFrequency $frequency): string
    {
        if ($frequency === TeamBillingFrequency::Weekly)
        {
            $fromLabel = $from->copy()->locale('es');
            $toLabel = $to->copy()->locale('es');

            if ($from->isSameMonth($to))
            {
                return 'Semana del '.$fromLabel->isoFormat('D').' al '.$toLabel->isoFormat('D [de] MMMM YYYY');
            }

            if ($from->isSameYear($to))
            {
                return 'Semana del '.$fromLabel->isoFormat('D [de] MMMM').' al '.$toLabel->isoFormat('D [de] MMMM YYYY');
            }

            return 'Semana del '.$fromLabel->isoFormat('D [de] MMMM YYYY').' al '.$toLabel->isoFormat('D [de] MMMM YYYY');
        }

        return $this->monthLabel($from);
    }

    private function monthLabel(Carbon $month): string
    {
        return ucfirst($month->copy()->locale('es')->translatedFormat('F Y'));
    }

    private function formatCount(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function formatCents(int $cents, string $currency): string
    {
        return Helpers::formatDecimal($cents / 100).' '.$currency;
    }
}
