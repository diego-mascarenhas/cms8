<?php

namespace App\Services\Finance;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurringPurchaseProformaIssuer
{
    public const REFERENCE_PREFIX = 'recurring-service-';

    /**
     * @return Collection<int, Invoice>
     */
    public function issueDue(?Team $team = null, bool $dryRun = false, ?Carbon $today = null): Collection
    {
        $today ??= now();
        $services = Service::withoutGlobalScopes()
            ->where('operation', 'buy')
            ->where('status', '>=', 4)
            ->whereNotNull('category_id')
            ->whereNotNull('next_billing')
            ->when($team !== null, function ($query) use ($team): void
            {
                $enterpriseIds = Enterprise::withoutGlobalScopes()
                    ->where('team_id', $team->id)
                    ->select('id');
                $query->whereIn('enterprise_id', $enterpriseIds);
            })
            ->get();

        $issued = collect();

        foreach ($services as $service)
        {
            $invoice = $this->issueForService($service, $dryRun, $today);

            if ($invoice !== null)
            {
                $issued->push($invoice);
            }
        }

        return $issued;
    }

    public function issueForService(Service $service, bool $dryRun = false, ?Carbon $today = null): ?Invoice
    {
        $today ??= now();
        $service = Service::withoutGlobalScopes()->find($service->id);

        if (! $service instanceof Service)
        {
            return null;
        }

        $enterprise = Enterprise::withoutGlobalScopes()->find($service->enterprise_id);

        if ($enterprise === null || (int) $enterprise->team_id <= 0)
        {
            return null;
        }

        if ((string) $service->operation !== 'buy' || (int) $service->status < 4 || $service->category_id === null || $service->next_billing === null)
        {
            return null;
        }

        $frequency = max(1, (int) ($service->frequency ?? 1));
        $when = $service->next_billing->copy()->startOfDay();
        $horizon = $today->copy()->endOfDay()->endOfMonth();
        $guard = 0;
        $openDraft = null;

        while ($guard < 36 && $when->lte($horizon))
        {
            $guard++;

            if ($service->expires_at !== null && $when->gt($service->expires_at))
            {
                break;
            }

            $existing = $this->findLinkedInvoice($service, $when);

            if ($existing === null)
            {
                if ($dryRun)
                {
                    return new Invoice([
                        'team_id' => $enterprise->team_id,
                        'enterprise_id' => $enterprise->id,
                        'number' => $this->proformaNumber($when),
                        'date' => $when->toDateString(),
                        'status' => 9,
                        'operation' => 'buy',
                    ]);
                }

                return $this->createDraft($service, $enterprise, $when);
            }

            if ((int) $existing->status === 9)
            {
                $openDraft = $existing;

                break;
            }

            if (in_array((int) $existing->status, [3, 4, 6, 7], true))
            {
                break;
            }

            $when = $when->copy()->addMonthsNoOverflow($frequency);

            if (! $dryRun)
            {
                $service->forceFill([
                    'last_billed' => $existing->date,
                    'next_billing' => $when->toDateString(),
                ])->save();
            }
        }

        return $openDraft;
    }

    public function isOverdue(Service $service): bool
    {
        if ((string) $service->operation !== 'buy' || (int) $service->status < 4 || $service->category_id === null || $service->next_billing === null)
        {
            return false;
        }

        if ($service->expires_at !== null && $service->next_billing->gt($service->expires_at))
        {
            return false;
        }

        return $service->next_billing->copy()->startOfDay()->lt(now()->startOfDay());
    }

    public function createProformaAndAdvance(Service $service): ?Invoice
    {
        $service = Service::withoutGlobalScopes()->find($service->id);

        if (! $service instanceof Service || ! $this->isOverdue($service))
        {
            return null;
        }

        $enterprise = Enterprise::withoutGlobalScopes()->find($service->enterprise_id);

        if ($enterprise === null || (int) $enterprise->team_id <= 0)
        {
            return null;
        }

        $when = $service->next_billing->copy()->startOfDay();
        $invoice = $this->findLinkedInvoice($service, $when) ?? $this->createDraft($service, $enterprise, $when);
        $next = $when->copy()->addMonthsNoOverflow(max(1, (int) ($service->frequency ?? 1)));

        $service->forceFill([
            'last_billed' => $when->toDateString(),
            'next_billing' => $next->toDateString(),
        ])->save();

        return $invoice;
    }

    public function syncAfterInvoice(Invoice $invoice): ?Invoice
    {
        $serviceId = $this->serviceIdFromReference((string) $invoice->source_reference_id);

        if ($serviceId === null)
        {
            return null;
        }

        $service = Service::withoutGlobalScopes()->find($serviceId);

        return $service instanceof Service ? $this->issueForService($service) : null;
    }

    public static function referenceFor(int $serviceId, Carbon $when): string
    {
        return self::REFERENCE_PREFIX.$serviceId.'-'.$when->format('Y-m');
    }

    private function findLinkedInvoice(Service $service, Carbon $when): ?Invoice
    {
        return Invoice::withoutGlobalScopes()
            ->where('source_provider', 'manual')
            ->where('source_reference_id', self::referenceFor((int) $service->id, $when))
            ->first();
    }

    private function createDraft(Service $service, Enterprise $enterprise, Carbon $when): Invoice
    {
        $amount = $this->cycleAmount($service);
        $currencyId = $this->currencyId($service);
        $concept = $this->concept($service, $when);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => (int) $enterprise->team_id,
            'enterprise_id' => (int) $enterprise->id,
            'billing_id' => null,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => $this->proformaNumber($when),
            'date' => $when->toDateString(),
            'due_date' => $when->toDateString(),
            'gross_amount' => $amount,
            'discount' => 0,
            'total_amount' => $amount,
            'balance' => $amount,
            'currency_id' => $currencyId,
            'status' => 9,
            'source_provider' => 'manual',
            'source_reference_id' => self::referenceFor((int) $service->id, $when),
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => (int) $service->category_id,
            'description' => $concept,
            'quantity' => 1,
            'unit_price' => $amount,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        return $invoice;
    }

    private function cycleAmount(Service $service): float
    {
        $price = (float) $service->price;
        $discount = min(100, max(0, (float) ($service->discount ?? 0)));

        return round(max($price - ($price * ($discount / 100)), 0.01), 2);
    }

    private function currencyId(Service $service): ?int
    {
        if ($service->currency_id !== null && Currency::query()->whereKey($service->currency_id)->exists())
        {
            return (int) $service->currency_id;
        }

        $euro = Currency::query()->where('code', 'EUR')->value('id');

        return $euro !== null ? (int) $euro : null;
    }

    private function concept(Service $service, Carbon $when): string
    {
        $description = trim((string) $service->description);
        $period = $service->frequencyLabel();
        $month = $when->copy()->locale('es')->isoFormat('MMMM YYYY');
        $base = $description !== '' ? $description.' — '.$period : $period;

        return mb_substr($base.' · '.$month, 0, 255);
    }

    private function proformaNumber(Carbon $when): string
    {
        return 'Proforma '.$when->format('Y-m');
    }

    private function serviceIdFromReference(string $reference): ?int
    {
        if (! str_starts_with($reference, self::REFERENCE_PREFIX))
        {
            return null;
        }

        $rest = substr($reference, strlen(self::REFERENCE_PREFIX));
        $serviceId = (int) explode('-', $rest, 2)[0];

        return $serviceId > 0 ? $serviceId : null;
    }
}
