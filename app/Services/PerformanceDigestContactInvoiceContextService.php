<?php

namespace App\Services;

use App\Helpers\Helpers;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PerformanceDigestContactInvoiceContextService
{
    /**
     * @return array{
     *     variant: string,
     *     count: int,
     *     overdue_count: int,
     *     total_balance: string,
     *     invoice_number: string,
     *     due_label: string,
     *     primary_invoice_id: int,
     *     enterprise_name: string,
     *     last_invoice_date: string
     * }|null
     */
    public function forContact(Team $team, ?Contact $contact): ?array
    {
        return $this->forContactAndMessage($team, $contact, '');
    }

    /**
     * @return array{
     *     variant: string,
     *     count: int,
     *     overdue_count: int,
     *     total_balance: string,
     *     invoice_number: string,
     *     due_label: string,
     *     primary_invoice_id: int,
     *     enterprise_name: string,
     *     last_invoice_date: string
     * }|null
     */
    public function forContactAndMessage(Team $team, ?Contact $contact, string $messageBody): ?array
    {
        if ($contact === null || ! $team->hasModule('invoices'))
        {
            return null;
        }

        $enterpriseIds = $this->enterpriseIdsForContact($contact);
        $billingInquiry = $this->isBillingInquiry($messageBody);

        if ($enterpriseIds === [])
        {
            return $billingInquiry ? $this->billingNoEnterpriseContext() : null;
        }

        $unpaidContext = $this->unpaidInvoicesContext($team, $enterpriseIds);
        if ($unpaidContext !== null)
        {
            return $unpaidContext;
        }

        if (! $billingInquiry)
        {
            return null;
        }

        return $this->billingInquiryContext($team, $enterpriseIds);
    }

    private function isBillingInquiry(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '')
        {
            return false;
        }

        return (bool) preg_match(
            '/\b(facturaci[oó]n|facturar|facturas?|billing|invoice|invoicing|cobros?|impagad[oa]s?|pendiente\s+de\s+pago|informaci[oó]n\s+sobre\s+factur)\b/ui',
            $normalized,
        );
    }

    /**
     * @param  list<int>  $enterpriseIds
     * @return array{
     *     variant: string,
     *     count: int,
     *     overdue_count: int,
     *     total_balance: string,
     *     invoice_number: string,
     *     due_label: string,
     *     primary_invoice_id: int,
     *     enterprise_name: string,
     *     last_invoice_date: string
     * }|null
     */
    private function unpaidInvoicesContext(Team $team, array $enterpriseIds): ?array
    {
        $today = now()->startOfDay();

        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::withoutGlobalScopes()
            ->with('enterprise:id,name')
            ->where('team_id', $team->id)
            ->where('balance', '>', 0)
            ->whereIn('enterprise_id', $enterpriseIds)
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND DATE(due_date) < ? THEN 0 ELSE 1 END', [$today->toDateString()])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        if ($invoices->isEmpty())
        {
            return null;
        }

        $primary = $invoices->first();
        $currency = (string) ($primary->currency ?: 'EUR');
        $totalBalance = round((float) $invoices->sum('balance'), 2);
        $overdueCount = $invoices->filter(function (Invoice $invoice) use ($today): bool
        {
            if ($invoice->due_date === null)
            {
                return false;
            }

            return Carbon::parse($invoice->due_date)->startOfDay()->lt($today);
        })->count();

        $variant = match (true)
        {
            $invoices->count() > 1 => 'multiple',
            $overdueCount > 0 => 'single_overdue',
            default => 'single_pending',
        };

        return [
            'variant' => $variant,
            'count' => $invoices->count(),
            'overdue_count' => $overdueCount,
            'total_balance' => Helpers::formatMoney($totalBalance, $currency),
            'invoice_number' => (string) ($primary->number ?: '#'.$primary->id),
            'due_label' => $this->formatDueLabel($primary->due_date, $today),
            'primary_invoice_id' => (int) $primary->id,
            'enterprise_name' => (string) ($primary->enterprise?->name ?? ''),
            'last_invoice_date' => $primary->date !== null
                ? Carbon::parse($primary->date)->format('d/m/Y')
                : '',
        ];
    }

    /**
     * @param  list<int>  $enterpriseIds
     * @return array{
     *     variant: string,
     *     count: int,
     *     overdue_count: int,
     *     total_balance: string,
     *     invoice_number: string,
     *     due_label: string,
     *     primary_invoice_id: int,
     *     enterprise_name: string,
     *     last_invoice_date: string
     * }
     */
    private function billingInquiryContext(Team $team, array $enterpriseIds): array
    {
        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::withoutGlobalScopes()
            ->with('enterprise:id,name')
            ->where('team_id', $team->id)
            ->whereIn('enterprise_id', $enterpriseIds)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($invoices->isEmpty())
        {
            $enterpriseName = $this->resolveEnterpriseName($team, $enterpriseIds);

            return [
                'variant' => 'billing_no_invoices',
                'count' => 0,
                'overdue_count' => 0,
                'total_balance' => '',
                'invoice_number' => '',
                'due_label' => '',
                'primary_invoice_id' => 0,
                'enterprise_name' => $enterpriseName,
                'last_invoice_date' => '',
            ];
        }

        $latest = $invoices->first();
        $currency = (string) ($latest->currency ?: 'EUR');
        $enterpriseName = (string) ($latest->enterprise?->name ?? $this->resolveEnterpriseName($team, $enterpriseIds));

        return [
            'variant' => 'billing_up_to_date',
            'count' => $invoices->count(),
            'overdue_count' => 0,
            'total_balance' => Helpers::formatMoney(round((float) $invoices->sum('total_amount'), 2), $currency),
            'invoice_number' => (string) ($latest->number ?: '#'.$latest->id),
            'due_label' => '',
            'primary_invoice_id' => (int) $latest->id,
            'enterprise_name' => $enterpriseName,
            'last_invoice_date' => $latest->date !== null
                ? Carbon::parse($latest->date)->format('d/m/Y')
                : '',
        ];
    }

    /**
     * @return array{
     *     variant: string,
     *     count: int,
     *     overdue_count: int,
     *     total_balance: string,
     *     invoice_number: string,
     *     due_label: string,
     *     primary_invoice_id: int,
     *     enterprise_name: string,
     *     last_invoice_date: string
     * }
     */
    private function billingNoEnterpriseContext(): array
    {
        return [
            'variant' => 'billing_no_enterprise',
            'count' => 0,
            'overdue_count' => 0,
            'total_balance' => '',
            'invoice_number' => '',
            'due_label' => '',
            'primary_invoice_id' => 0,
            'enterprise_name' => '',
            'last_invoice_date' => '',
        ];
    }

    /**
     * @param  list<int>  $enterpriseIds
     */
    private function resolveEnterpriseName(Team $team, array $enterpriseIds): string
    {
        $id = $enterpriseIds[0] ?? 0;
        if ($id <= 0)
        {
            return '';
        }

        return (string) (\App\Models\Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('id', $id)
            ->value('name') ?? '');
    }

    private function formatDueLabel(mixed $dueDate, CarbonInterface $today): string
    {
        if ($dueDate === null || $dueDate === '')
        {
            return (string) __('app.performance_digest_invoice_no_due_date');
        }

        $due = Carbon::parse($dueDate)->startOfDay();

        if ($due->lt($today))
        {
            return (string) __('app.performance_digest_invoice_due_overdue', [
                'date' => $due->format('d/m/Y'),
            ]);
        }

        return (string) __('app.performance_digest_invoice_due_on', [
            'date' => $due->format('d/m/Y'),
        ]);
    }

    /**
     * @return list<int>
     */
    private function enterpriseIdsForContact(Contact $contact): array
    {
        $ids = [];
        if ((int) $contact->current_enterprise_id > 0)
        {
            $ids[] = (int) $contact->current_enterprise_id;
        }

        $linked = $contact->enterprises()->pluck('enterprises.id')->all();

        return array_values(array_unique(array_filter(array_map('intval', array_merge($ids, $linked)))));
    }
}
