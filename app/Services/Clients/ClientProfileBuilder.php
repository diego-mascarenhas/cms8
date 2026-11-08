<?php

namespace App\Services\Clients;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Services\Stripe\CustomerPaymentMethodReader;
use App\Services\Stripe\SubscriptionScheduleReader;
use App\Support\ClientHeadlineMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClientProfileBuilder
{
    public function __construct(
        private CustomerPaymentMethodReader $paymentMethods,
        private SubscriptionScheduleReader $schedules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Enterprise $client): array
    {
        $client->load([
            'enterpriseBillingAddresses.taxStatusType',
            'contacts',
            'projects.responsible',
            'projects.status',
            'services.currency',
            'services.category',
            'invoices.currency',
            'invoices.stripeInvoiceSync',
        ]);

        $subscriptions = $this->subscriptions($client);
        $services = $client->services
            ->filter(fn (Service $service): bool => $service->subscription_id === null)
            ->sortBy('id')
            ->values();
        $consumptions = filled($client->code)
            ? $client->usageInvoiceSyncs()->orderByDesc('invoice_created_at')->orderByDesc('id')->get()
            : collect();
        $invoices = $client->invoices->sort(function (Invoice $left, Invoice $right): int
        {
            $byPriority = $left->listStatusSortPriority() <=> $right->listStatusSortPriority();
            if ($byPriority !== 0)
            {
                return $byPriority;
            }

            return $right->id <=> $left->id;
        })->values();
        $paymentCard = $this->paymentMethods->forCustomer($client->team, (string) $client->code);
        $closed = ProjectStatus::closedStatusIds();
        $activeProjects = $client->projects
            ->filter(fn (Project $project): bool => ! in_array($project->status_id, $closed, true))
            ->sortByDesc('id')
            ->values();
        $pastProjects = $client->projects
            ->filter(fn (Project $project): bool => in_array($project->status_id, $closed, true))
            ->sortByDesc('id')
            ->values();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'style_guide' => trim((string) data_get($client->data, 'style_guide')),
            'archived' => $client->trashed(),
            'headline' => ClientHeadlineMetrics::cards($invoices, $subscriptions, $paymentCard),
            'billing' => $this->billingRows($client, $subscriptions),
            'contacts' => $client->contacts->sortBy('name')->values()->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => trim($contact->name.' '.($contact->surname ?? '')),
                'email' => (string) ($contact->email ?? ''),
                'phone' => (string) ($contact->phone ?? ''),
            ])->all(),
            'subscriptions' => $subscriptions->map(fn (ServiceSync $sync): array => $this->subscriptionRow($sync))->all(),
            'consumptions' => $consumptions->map(fn (InvoiceSync $row): array => $this->consumptionRow($row))->all(),
            'services' => $services->map(fn (Service $service): array => $this->serviceRow($service))->all(),
            'invoices' => $invoices->map(fn (Invoice $invoice): array => $this->invoiceRow($invoice))->all(),
            'invoice_balance' => number_format((float) $client->invoices->sum('balance'), 2, ',', '.'),
            'projects' => [
                'active' => $activeProjects->map(fn (Project $project): array => $this->projectRow($project, false))->all(),
                'past' => $pastProjects->take(6)->map(fn (Project $project): array => $this->projectRow($project, true))->all(),
                'past_total' => $pastProjects->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, ServiceSync>
     */
    private function subscriptions(Enterprise $client): Collection
    {
        if (! filled($client->code))
        {
            return collect();
        }

        return $client->serviceSyncs()->get()
            ->concat($this->schedules->upcomingForCustomer($client->team, (string) $client->code))
            ->unique('stripe_id')
            ->sortBy(function (ServiceSync $sync): array
            {
                $rank = [
                    'past_due' => 1,
                    'unpaid' => 2,
                    'incomplete' => 3,
                    'incomplete_expired' => 4,
                    'trialing' => 5,
                    'active' => 6,
                    'not_started' => 7,
                    'paused' => 8,
                    'canceled' => 9,
                ];

                return [
                    $rank[strtolower(trim((string) $sync->status))] ?? 99,
                    $sync->clientFacingName(),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, ServiceSync>  $subscriptions
     * @return list<array{name: string, tax_id: string, tax_status: string, country: string, active: bool}>
     */
    private function billingRows(Enterprise $client, Collection $subscriptions): array
    {
        $fallback = trim((string) $client->country);
        if ($fallback === '')
        {
            $fallback = $subscriptions
                ->map(fn (ServiceSync $sync): string => trim((string) $sync->customer_country))
                ->first(fn (string $code): bool => $code !== '') ?? '';
        }

        return $client->enterpriseBillingAddresses
            ->sortByDesc('status')
            ->values()
            ->map(fn (EnterpriseBillingAddress $billing): array => [
                'name' => (string) $billing->name,
                'tax_id' => (string) ($billing->identification_number ?? ''),
                'tax_status' => (string) ($billing->taxStatusType?->label() ?? ''),
                'country' => $billing->countryLabel($fallback),
                'active' => (int) $billing->status === 1,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionRow(ServiceSync $sync): array
    {
        $status = $this->badgeParts($sync->status_badge);
        $next = $sync->current_period_end;

        return [
            'id' => $sync->id,
            'name' => $sync->clientFacingName(),
            'amount' => $sync->amount_total !== null
                ? number_format((float) $sync->amount_total, 2, ',', '.').' '.strtoupper((string) $sync->price_currency)
                : '',
            'discount' => $sync->clientDiscountLabel(),
            'frequency' => $sync->clientFrequencyLabel(),
            'next' => $next instanceof Carbon ? $next->timezone('Europe/Madrid')->format('d/m/Y') : '',
            'payment_method' => $sync->clientPaymentMethodLabel(),
            'status' => $status['label'],
            'status_tone' => $status['tone'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consumptionRow(InvoiceSync $row): array
    {
        $status = $this->badgeParts($row->status_badge);
        $date = $row->invoice_created_at;

        return [
            'date' => $date instanceof Carbon ? $date->format('d/m/Y') : '',
            'summary' => $row->consumptionSummary(),
            'amount' => $row->total !== null
                ? number_format((float) $row->total, 2, ',', '.').' '.strtoupper((string) $row->currency)
                : '',
            'status' => $status['label'],
            'status_tone' => $status['tone'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceRow(Service $service): array
    {
        $status = $this->badgeParts((string) $service->status_label);
        $frequency = (int) ($service->frequency ?? 1);
        $currency = $service->currency;
        $code = (string) ($currency?->code ?? $currency?->symbol ?? '');
        $next = $service->next_billing;

        return [
            'id' => $service->id,
            'description' => (string) ($service->description ?: ($service->service_name ?? '')),
            'plan' => (string) ($service->category?->name ?? ''),
            'amount' => $service->price !== null && (float) $service->price !== 0.0
                ? number_format((float) $service->price, 2, ',', '.').' '.$code
                : '',
            'frequency' => match ($frequency)
            {
                1 => 'Mensual',
                3 => 'Trimestral',
                6 => 'Semestral',
                12 => 'Anual',
                default => $frequency > 0 ? $frequency.' mes(es)' : '',
            },
            'next' => $next ? Carbon::parse($next)->format('d/m/Y') : '',
            'status' => $status['label'],
            'status_tone' => $status['tone'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $invoice): array
    {
        $status = $this->badgeParts($invoice->status_badge);

        return [
            'id' => $invoice->id,
            'number' => (string) ($invoice->number ?? ''),
            'date' => $invoice->date ? Carbon::parse($invoice->date)->format('d/m/Y') : '',
            'due' => $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '',
            'total' => number_format((float) ($invoice->total_amount ?? 0), 2, ',', '.').' '.$invoice->currency_code,
            'balance' => number_format((float) ($invoice->balance ?? 0), 2, ',', '.').' '.$invoice->currency_code,
            'status' => $status['label'],
            'status_tone' => $status['tone'],
            'url' => $this->hostedInvoiceUrl($invoice),
        ];
    }

    private function hostedInvoiceUrl(Invoice $invoice): ?string
    {
        $url = trim((string) $invoice->stripeHostedInvoiceUrl());

        return str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectRow(Project $project, bool $past): array
    {
        $from = $project->date_start
            ? Carbon::parse($project->date_start)
            : ($project->created_at ? Carbon::parse($project->created_at) : null);
        $to = $past
            ? ($project->date_end ? Carbon::parse($project->date_end) : ($project->updated_at ? Carbon::parse($project->updated_at) : null))
            : null;

        return [
            'id' => $project->id,
            'name' => (string) $project->name,
            'elapsed' => $from ? $from->locale('es')->diffForHumans($to ?? now(), ['syntax' => Carbon::DIFF_ABSOLUTE]) : '',
            'responsible' => (string) ($project->responsible?->name ?? ''),
            'end' => $project->date_end ? Carbon::parse($project->date_end)->format('d/m/Y') : '',
            'status' => (string) ($project->status->translated_name ?? ''),
        ];
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function badgeParts(string $html): array
    {
        $tone = 'secondary';
        if (preg_match('/bg-label-([a-z]+)/', $html, $match) === 1)
        {
            $tone = $match[1];
        }

        return [
            'label' => trim(html_entity_decode(strip_tags($html))),
            'tone' => $tone,
        ];
    }
}
