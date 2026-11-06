<?php

namespace App\Services;

use App\Enums\TeamBillingFrequency;
use App\Helpers\Helpers;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Models\TeamUsageInvoiceAdjustment;
use App\Services\Billing\ClientTokenPresenter;
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
     *     prospect_credits: int,
     *     prospect_billed_cents: int,
     *     storage_bytes: int,
     *     storage_billed_cents: int,
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

        return $this->forPeriod($team, $from, $to, $isCurrent, TeamBillingFrequency::Monthly, $isCurrent);
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
     *     prospect_credits: int,
     *     prospect_billed_cents: int,
     *     storage_bytes: int,
     *     storage_billed_cents: int,
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
        [$from, $closesOn] = TeamUsageInvoiceFrequency::window($team, $frequency);
        $to = now()->lt($closesOn) ? now() : $closesOn->copy()->subSecond();
        if ($from->gt($to))
        {
            $to = $from->copy();
        }
        $usage = $this->forPeriod($team, $from, $to, true, $frequency, true);
        $adjustments = $this->pendingAdjustments($team);
        $totalBilledCents = $usage['billed_cents'] + (int) $adjustments->sum('billed_cents');
        $totalCostCents = $usage['cost_cents'] + (int) $adjustments->sum('cost_cents');
        $totalMarkupCents = $usage['markup_cents'] + (int) $adjustments->sum('markup_cents');
        $totalTokensReal = $usage['tokens_real'] + (int) $adjustments->sum('tokens_real');
        $totalTokensBilled = $usage['tokens_billed'] + (int) $adjustments->sum('tokens_billed');
        $periodLabel = $this->periodLabel($from, $closesOn, $frequency);
        $invoices = $this->invoiceDocuments($adjustments->all(), $usage, $periodLabel, $frequency);

        return array_merge($usage, [
            'frequency' => $frequency->value,
            'frequency_label' => $frequency->label(),
            'closes_on' => $closesOn->format('d/m/Y'),
            'period_label' => $periodLabel,
            'adjustments' => $adjustments->all(),
            'has_adjustments' => $adjustments->isNotEmpty(),
            'invoices' => $invoices,
            'invoice_lines' => $this->flattenInvoiceLines($invoices),
            'total_billed_cents' => $totalBilledCents,
            'total_cost_cents' => $totalCostCents,
            'total_markup_cents' => $totalMarkupCents,
            'total_tokens_real' => $totalTokensReal,
            'total_tokens_billed' => $totalTokensBilled,
            'formatted' => array_merge($usage['formatted'], [
                'total_billed' => $this->formatCents($totalBilledCents, $usage['currency']),
                'total_cost' => $this->formatCents($totalCostCents, $usage['currency']),
                'total_markup' => $this->formatCents($totalMarkupCents, $usage['currency']),
                'total_tokens' => $this->formatCount($totalTokensBilled),
            ]),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function frequencyChangePreviews(Team $team): array
    {
        $previews = [];

        foreach (TeamBillingFrequency::cases() as $frequency)
        {
            $previews[$frequency->value] = $this->simulateFrequencyChange($team, $frequency);
        }

        return $previews;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function simulateFrequencyChange(Team $team, TeamBillingFrequency $next): ?array
    {
        $current = TeamUsageInvoiceFrequency::for($team);
        if ($current === $next)
        {
            return null;
        }

        $now = now()->copy();
        [$from] = TeamUsageInvoiceFrequency::window($team, $current, $now);
        $nextStart = $now->copy()->startOfDay();
        $invoices = [];

        if ($from->lt($nextStart))
        {
            $to = $nextStart->copy()->subSecond();
            $usage = $this->forPeriod($team, $from, $to, false, $current);
            $periodLabel = $this->adjustmentLabel($from, $to);
            $invoices[] = $this->invoiceDocument(
                'Ajuste '.$current->label(),
                $periodLabel,
                $usage,
                'Se cierra al guardar. Aún no se emite.',
            );
        }

        [$newFrom, $newCloses] = $next === TeamBillingFrequency::Weekly
            ? TeamUsageInvoiceFrequency::weeklyWindow($nextStart, $now)
            : TeamUsageInvoiceFrequency::monthlyWindow($nextStart, $nextStart->day, $now);
        $newTo = $now->lt($newCloses) ? $now : $newCloses->copy()->subSecond();
        if ($newFrom->gt($newTo))
        {
            $newTo = $newFrom->copy();
        }
        $newUsage = $this->forPeriod($team, $newFrom, $newTo, true, $next, true);
        $newLabel = $this->periodLabel($newFrom, $newCloses, $next);
        $invoices[] = $this->invoiceDocument(
            $next->label(),
            $newLabel,
            $newUsage,
            'Arranca hoy. Se emitirá al cierre.',
        );

        $tokensReal = (int) collect($invoices)->sum('tokens_real');
        $tokensBilled = (int) collect($invoices)->sum('tokens_billed');

        return [
            'from_frequency' => $current->label(),
            'to_frequency' => $next->label(),
            'invoices' => $invoices,
            'invoice_lines' => $this->flattenInvoiceLines($invoices),
            'tokens_real' => $tokensReal,
            'tokens_billed' => $tokensBilled,
            'formatted_tokens' => $this->formatCount($tokensBilled),
            'total_billed_cents' => (int) collect($invoices)->sum('billed_cents'),
            'formatted_total' => $this->formatCents((int) collect($invoices)->sum('billed_cents'), $newUsage['currency']),
        ];
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
     *     prospect_credits: int,
     *     prospect_billed_cents: int,
     *     storage_bytes: int,
     *     storage_billed_cents: int,
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
     * @return array<string, mixed>
     */
    public function forClosedWindow(Team $team, Carbon $from, Carbon $closesOn, TeamBillingFrequency $frequency): array
    {
        $to = $closesOn->copy()->subSecond();
        if ($to->lt($from))
        {
            $to = $from->copy();
        }

        return $this->forPeriod($team, $from, $to, false, $frequency, true);
    }

    /**
     * Open consumption cycle (tokens, WhatsApp, mail, prospection, storage) without calling Stripe.
     *
     * @return array<string, mixed>
     */
    public function forOpenWindow(Team $team, ?Carbon $closesOn = null): array
    {
        $frequency = TeamUsageInvoiceFrequency::for($team);

        if ($closesOn === null)
        {
            [$from, $closesOn] = TeamUsageInvoiceFrequency::calendarWindow($frequency);
        } elseif ($frequency === TeamBillingFrequency::Weekly)
        {
            $from = $closesOn->copy()->subWeek();
        } else
        {
            $from = TeamUsageInvoiceFrequency::previousMonthlyAnniversary($closesOn, $closesOn->day);
        }

        $to = now()->lt($closesOn) ? now() : $closesOn->copy()->subSecond();
        if ($from->gt($to))
        {
            $to = $from->copy();
        }

        return $this->forPeriod($team, $from, $to, true, $frequency, true);
    }

    /**
     * Stripe lines: the billable items, skipping zero amounts.
     *
     * @param  array<string, mixed>  $usage
     * @return list<array{kind: string, description: string, detail: string, quantity: int, amount_cents: int, formatted_amount: string}>
     */
    public function billableLines(array $usage, string $periodLabel): array
    {
        return array_values(array_filter(
            $this->invoiceLines($usage, $periodLabel),
            fn (array $line): bool => $line['kind'] !== 'token_source' && $line['amount_cents'] > 0,
        ));
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
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingAdjustments(Team $team): Collection
    {
        return TeamUsageInvoiceAdjustment::query()
            ->pending()
            ->where('team_id', $team->id)
            ->orderBy('period_from')
            ->get()
            ->map(function (TeamUsageInvoiceAdjustment $adjustment) use ($team): array
            {
                $from = $adjustment->period_from->copy();
                $to = $adjustment->period_to->copy()->subSecond();
                if ($to->lt($from))
                {
                    $to = $from->copy();
                }

                $usage = $this->forPeriod($team, $from, $to, false, $adjustment->frequency);

                return array_merge($usage, [
                    'id' => $adjustment->id,
                    'kind' => 'adjustment',
                    'frequency' => $adjustment->frequency->value,
                    'frequency_label' => $adjustment->frequency->label(),
                    'period_label' => $this->adjustmentLabel($from, $to),
                ]);
            })
            ->filter(fn (array $row): bool => $this->monthHasConsumption($row))
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $adjustments
     * @param  array<string, mixed>  $current
     * @return list<array<string, mixed>>
     */
    private function invoiceDocuments(array $adjustments, array $current, string $periodLabel, TeamBillingFrequency $frequency): array
    {
        $documents = [];

        foreach ($adjustments as $adjustment)
        {
            $documents[] = $this->invoiceDocument(
                'Ajuste '.$adjustment['frequency_label'],
                $adjustment['period_label'],
                $adjustment,
                'Pendiente de emitir.',
            );
        }

        $documents[] = $this->invoiceDocument(
            $frequency->label(),
            $periodLabel,
            $current,
            'Aún no se cierra.',
        );

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array<string, mixed>
     */
    private function invoiceDocument(string $title, string $periodLabel, array $usage, string $note): array
    {
        return [
            'title' => $title,
            'period_label' => $periodLabel,
            'note' => $note,
            'tokens_real' => $usage['tokens_real'],
            'tokens_billed' => $usage['tokens_billed'],
            'formatted_tokens' => $usage['formatted']['tokens'],
            'billed_cents' => $usage['billed_cents'],
            'formatted_total' => $usage['formatted']['billed'],
            'lines' => $this->invoiceLines($usage, $periodLabel),
        ];
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return list<array{kind: string, description: string, detail: string, quantity: int, amount_cents: int, formatted_amount: string}>
     */
    private function invoiceLines(array $usage, string $periodLabel): array
    {
        $lines = [
            [
                'kind' => 'tokens',
                'description' => 'Tokens IA · '.$periodLabel,
                'detail' => $usage['formatted']['tokens'],
                'quantity' => max(1, (int) $usage['tokens_billed']),
                'amount_cents' => $usage['token_billed_cents'],
                'formatted_amount' => $usage['formatted']['token_billed'],
            ],
        ];

        foreach ($usage['tokens_by_module'] ?? [] as $source)
        {
            $lines[] = [
                'kind' => 'token_source',
                'description' => $source['name'],
                'detail' => $source['formatted'],
                'quantity' => max(1, (int) ($source['tokens_billed'] ?? 1)),
                'amount_cents' => 0,
                'formatted_amount' => '',
            ];
        }

        $lines[] = [
            'kind' => 'whatsapp',
            'description' => 'Envíos WhatsApp · '.$periodLabel,
            'detail' => $this->formatCount((int) $usage['whatsapp_messages']).' envíos',
            'quantity' => max(1, (int) $usage['whatsapp_messages']),
            'amount_cents' => $usage['whatsapp_billed_cents'],
            'formatted_amount' => $usage['formatted']['whatsapp_billed'],
        ];
        $lines[] = [
            'kind' => 'mailer',
            'description' => 'Envíos email · '.$periodLabel,
            'detail' => $this->formatCount((int) $usage['mailer_emails']).' envíos',
            'quantity' => max(1, (int) $usage['mailer_emails']),
            'amount_cents' => $usage['mailer_billed_cents'],
            'formatted_amount' => $usage['formatted']['mailer_billed'],
        ];
        $lines[] = [
            'kind' => 'prospect',
            'description' => 'Prospección · '.$periodLabel,
            'detail' => $this->formatCount((int) ($usage['prospect_credits'] ?? 0)).' créditos',
            'quantity' => max(1, (int) ($usage['prospect_credits'] ?? 0)),
            'amount_cents' => (int) ($usage['prospect_billed_cents'] ?? 0),
            'formatted_amount' => $usage['formatted']['prospect_billed'] ?? $this->formatCents(0, (string) ($usage['currency'] ?? 'USD')),
        ];
        $lines[] = [
            'kind' => 'storage',
            'description' => 'Almacenamiento · '.$periodLabel,
            'detail' => (string) ($usage['formatted']['storage_size'] ?? '0 B'),
            'quantity' => max(1, (int) ($usage['storage_bytes'] ?? 0)),
            'amount_cents' => (int) ($usage['storage_billed_cents'] ?? 0),
            'formatted_amount' => $usage['formatted']['storage_billed'] ?? $this->formatCents(0, (string) ($usage['currency'] ?? 'USD')),
        ];
        $lines[] = [
            'kind' => 'transcription',
            'description' => 'Transcripción · '.$periodLabel,
            'detail' => (string) ($usage['formatted']['transcription_duration'] ?? '0 s'),
            'quantity' => max(1, (int) ($usage['transcription_seconds'] ?? 0)),
            'amount_cents' => (int) ($usage['transcription_billed_cents'] ?? 0),
            'formatted_amount' => $usage['formatted']['transcription_billed'] ?? $this->formatCents(0, (string) ($usage['currency'] ?? 'USD')),
        ];
        $lines[] = [
            'kind' => 'voice',
            'description' => 'Voz ElevenLabs · '.$periodLabel,
            'detail' => $this->formatCount((int) ($usage['voice_characters'] ?? 0)).' caracteres',
            'quantity' => max(1, (int) ($usage['voice_characters'] ?? 0)),
            'amount_cents' => (int) ($usage['voice_billed_cents'] ?? 0),
            'formatted_amount' => $usage['formatted']['voice_billed'] ?? $this->formatCents(0, (string) ($usage['currency'] ?? 'USD')),
        ];
        $videoCount = (int) ($usage['videos'] ?? 0);
        $lines[] = [
            'kind' => 'video',
            'description' => 'Vídeos de anuncio · '.$periodLabel,
            'detail' => $this->formatCount($videoCount).($videoCount === 1 ? ' vídeo' : ' vídeos'),
            'quantity' => max(1, (int) ($usage['videos'] ?? 0)),
            'amount_cents' => (int) ($usage['video_billed_cents'] ?? 0),
            'formatted_amount' => $usage['formatted']['video_billed'] ?? $this->formatCents(0, (string) ($usage['currency'] ?? 'USD')),
        ];

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     * @return list<array<string, mixed>>
     */
    private function flattenInvoiceLines(array $invoices): array
    {
        $lines = [];

        foreach ($invoices as $invoice)
        {
            foreach ($invoice['lines'] as $line)
            {
                $lines[] = array_merge($line, [
                    'invoice' => $invoice['title'],
                ]);
            }
        }

        return $lines;
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
        bool $billStorage = false,
    ): array {
        $asOf = $isCurrent ? $to->copy() : $from->copy();

        $this->tokens->usingTeam($team);
        $stats = TeamApiUsageStatsService::forTeam((int) $team->id, $from, $to);
        $realTokens = (int) $stats['totalTokensUsed'];
        $billedTokens = $this->tokens->scale($realTokens, $asOf);
        $tokenCostCents = $this->tokens->costCents($realTokens, 0, null, $asOf);
        $tokenBilledCents = $this->tokens->costCents($billedTokens, 0, null, $asOf);
        $tokenMarkupCents = max(0, $tokenBilledCents - $tokenCostCents);
        $tokensByModule = $this->tokensByModule($stats['byModule'] ?? [], $realTokens, $asOf);

        $whatsapp = TeamWhatsAppUsageStatsService::forTeam($team, $from, $to);
        $mailer = $this->mailerForPeriod($team, $from, $to, $asOf);
        $prospect = TeamProspectUsageStatsService::forTeam($team, $from, $to);
        $storage = TeamStorageUsageStatsService::forTeam($team, $to, $billStorage);
        $transcription = TeamTranscriptionUsageStatsService::forTeam($team, $from, $to);
        $paidAds = TeamPaidAdUsageStatsService::forTeam($team, $from, $to);
        $currency = TokenBillingRateService::displayCurrency();
        $multiplier = TokenBillingRateService::clientTokenMultiplier($team, $asOf);
        $whatsappCents = TokenBillingRateService::toDisplayCents((int) $whatsapp['our_amount_cents'], (string) ($whatsapp['currency'] ?? $currency), $asOf);
        $mailerCents = TokenBillingRateService::toDisplayCents((int) $mailer['billed_cents'], (string) ($mailer['currency'] ?? $currency), $asOf);
        $prospectCents = TokenBillingRateService::toDisplayCents((int) $prospect['our_amount_cents'], (string) ($prospect['currency'] ?? $currency), $asOf);
        $storageCents = TokenBillingRateService::toDisplayCents((int) $storage['our_amount_cents'], (string) ($storage['currency'] ?? $currency), $asOf);
        $transcriptionCents = TokenBillingRateService::toDisplayCents((int) $transcription['our_amount_cents'], (string) ($transcription['currency'] ?? $currency), $asOf);
        $voiceCents = TokenBillingRateService::toDisplayCents((int) $paidAds['voice_amount_cents'], (string) ($paidAds['currency'] ?? $currency), $asOf);
        $videoCents = TokenBillingRateService::toDisplayCents((int) $paidAds['video_amount_cents'], (string) ($paidAds['currency'] ?? $currency), $asOf);

        $costCents = $tokenCostCents;
        $billedCents = $tokenBilledCents
            + $whatsappCents
            + $mailerCents
            + $prospectCents
            + $storageCents
            + $transcriptionCents
            + $voiceCents
            + $videoCents;
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
            'tokens_by_module' => $tokensByModule,
            'token_cost_cents' => $tokenCostCents,
            'token_billed_cents' => $tokenBilledCents,
            'token_markup_cents' => $tokenMarkupCents,
            'whatsapp_messages' => (int) $whatsapp['messages_sent'],
            'whatsapp_billed_cents' => $whatsappCents,
            'mailer_emails' => $mailer['emails'],
            'mailer_overage' => $mailer['overage'],
            'mailer_billed_cents' => $mailerCents,
            'prospect_credits' => (int) $prospect['credits_used'],
            'prospect_billed_cents' => $prospectCents,
            'storage_bytes' => (int) $storage['bytes'],
            'storage_billed_cents' => $storageCents,
            'transcription_seconds' => (int) $transcription['audio_seconds'],
            'transcription_billed_cents' => $transcriptionCents,
            'voice_characters' => (int) $paidAds['characters'],
            'voice_billed_cents' => $voiceCents,
            'videos' => (int) $paidAds['videos'],
            'video_billed_cents' => $videoCents,
            'cost_cents' => $costCents,
            'billed_cents' => $billedCents,
            'markup_cents' => $markupCents,
            'currency' => $currency,
            'formatted' => [
                'tokens' => $this->formatCount($billedTokens),
                'tokens_real' => $this->formatCount($realTokens),
                'tokens_billed' => $this->formatCount($billedTokens),
                'tokens_by_module' => $this->formatTokenSources($tokensByModule),
                'multiplier' => TeamBillingRate::formatAmount($multiplier),
                'whatsapp' => $this->formatCount((int) $whatsapp['messages_sent']).' / '.$this->formatCents($whatsappCents, $currency),
                'mailer' => $this->formatCount($mailer['emails']).' / '.$this->formatCents($mailerCents, $currency),
                'prospect' => $this->formatCount((int) $prospect['credits_used']).' / '.$this->formatCents($prospectCents, $currency),
                'storage' => $storage['formatted_size'].' / '.$this->formatCents($storageCents, $currency),
                'storage_size' => $storage['formatted_size'],
                'transcription' => $transcription['formatted_duration'].' / '.$this->formatCents($transcriptionCents, $currency),
                'transcription_duration' => $transcription['formatted_duration'],
                'voice' => $this->formatCount((int) $paidAds['characters']).' / '.$this->formatCents($voiceCents, $currency),
                'video' => $this->formatCount((int) $paidAds['videos']).' / '.$this->formatCents($videoCents, $currency),
                'cost' => $this->formatCents($costCents, $currency),
                'billed' => $this->formatCents($billedCents, $currency),
                'markup' => $this->formatCents($markupCents, $currency),
                'token_cost' => $this->formatCents($tokenCostCents, $currency),
                'token_billed' => $this->formatCents($tokenBilledCents, $currency),
                'token_markup' => $this->formatCents($tokenMarkupCents, $currency),
                'whatsapp_billed' => $this->formatCents($whatsappCents, $currency),
                'mailer_billed' => $this->formatCents($mailerCents, $currency),
                'prospect_billed' => $this->formatCents($prospectCents, $currency),
                'storage_billed' => $this->formatCents($storageCents, $currency),
                'transcription_billed' => $this->formatCents($transcriptionCents, $currency),
                'voice_billed' => $this->formatCents($voiceCents, $currency),
                'video_billed' => $this->formatCents($videoCents, $currency),
            ],
        ];
    }

    /**
     * @param  array<string, array{module_name?: string, tokens_used?: int}>  $byModule
     * @return list<array{name: string, tokens_real: int, tokens_billed: int, formatted: string}>
     */
    private function tokensByModule(array $byModule, int $realTokens, Carbon $asOf): array
    {
        $rows = [];
        $assigned = 0;

        $named = collect($byModule)
            ->filter(fn (array $row): bool => (int) ($row['tokens_used'] ?? 0) > 0)
            ->sortByDesc(fn (array $row): int => (int) $row['tokens_used'])
            ->values();

        foreach ($named as $row)
        {
            $real = (int) $row['tokens_used'];
            $assigned += $real;
            $billed = $this->tokens->scale($real, $asOf);
            $rows[] = [
                'name' => (string) ($row['module_name'] ?? 'Otros'),
                'tokens_real' => $real,
                'tokens_billed' => $billed,
                'formatted' => $this->formatCount($billed),
            ];
        }

        $leftover = max(0, $realTokens - $assigned);
        if ($rows !== [] && $leftover > 0)
        {
            $billed = $this->tokens->scale($leftover, $asOf);
            $rows[] = [
                'name' => 'Otros',
                'tokens_real' => $leftover,
                'tokens_billed' => $billed,
                'formatted' => $this->formatCount($billed),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, tokens_billed: int}>  $sources
     */
    private function formatTokenSources(array $sources): string
    {
        return collect($sources)
            ->map(fn (array $source): string => $source['name'].' '.$this->formatCount((int) $source['tokens_billed']))
            ->implode(' · ');
    }

    /**
     * Plans do not include sends. Every delivered email in the window is billed from zero.
     *
     * @return array{emails: int, overage: int, billed_cents: int, currency: string}
     */
    private function mailerForPeriod(Team $team, Carbon $from, Carbon $to, Carbon $asOf): array
    {
        $stats = TeamMailerUsageStatsService::forTeam($team, $from, $to);

        return [
            'emails' => (int) $stats['emails_sent'],
            'overage' => (int) $stats['emails_sent'],
            'billed_cents' => (int) $stats['amount_due_cents'],
            'currency' => (string) ($stats['currency'] ?? TokenBillingRateService::displayCurrency()),
        ];
    }

    /**
     * @param  array{tokens_real: int, whatsapp_messages: int, mailer_emails: int, prospect_credits?: int, storage_bytes?: int, billed_cents: int}  $row
     */
    private function monthHasConsumption(array $row): bool
    {
        return $row['tokens_real'] > 0
            || $row['whatsapp_messages'] > 0
            || $row['mailer_emails'] > 0
            || (int) ($row['prospect_credits'] ?? 0) > 0
            || (int) ($row['storage_bytes'] ?? 0) > 0
            || (int) ($row['transcription_seconds'] ?? 0) > 0
            || (int) ($row['voice_characters'] ?? 0) > 0
            || (int) ($row['videos'] ?? 0) > 0
            || $row['billed_cents'] > 0;
    }

    private function adjustmentLabel(Carbon $from, Carbon $to): string
    {
        $fromLabel = $from->copy()->locale('es');
        $toLabel = $to->copy()->locale('es');

        if ($from->isSameMonth($to))
        {
            return $fromLabel->isoFormat('D').' al '.$toLabel->isoFormat('D [de] MMMM YYYY');
        }

        if ($from->isSameYear($to))
        {
            return $fromLabel->isoFormat('D [de] MMMM').' al '.$toLabel->isoFormat('D [de] MMMM YYYY');
        }

        return $fromLabel->isoFormat('D [de] MMMM YYYY').' al '.$toLabel->isoFormat('D [de] MMMM YYYY');
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

        $inclusiveEnd = $to->copy()->subSecond();
        if ($from->day === 1 && $from->isSameMonth($inclusiveEnd) && $from->isSameDay($from->copy()->startOfMonth()))
        {
            return $this->monthLabel($from);
        }

        return $this->adjustmentLabel($from, $to);
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
