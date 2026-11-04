<?php

namespace App\Services\Billing;

use App\Models\Service;
use App\Models\ServiceSync;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SubscriptionCatalogText
{
    /**
     * @param  Collection<int, ServiceSync>  $subscriptions
     * @param  callable(ServiceSync): string|null  $extra
     */
    public function grouped(Collection $subscriptions, ?callable $extra = null): string
    {
        if ($subscriptions->isEmpty())
        {
            return 'No hay suscripciones.';
        }

        $groups = $subscriptions
            ->sortBy(fn (ServiceSync $sync): string => ($this->isCurrent($sync) ? '0' : '1').mb_strtolower($sync->clientFacingName()))
            ->groupBy(fn (ServiceSync $sync): string => $sync->clientCategoryLabel());

        $lines = [];
        foreach ($this->orderedCategories($groups->keys()) as $category)
        {
            if ($lines !== [])
            {
                $lines[] = '';
            }

            $lines[] = $category;
            foreach ($groups->get($category, collect()) as $subscription)
            {
                $suffix = $extra !== null ? trim((string) $extra($subscription)) : '';
                $lines[] = '- '.$this->line($subscription, $suffix);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    public function groupedServices(Collection $services): string
    {
        $usable = $services
            ->filter(fn (Service $service): bool => $this->serviceLabel($service) !== '')
            ->values();

        if ($usable->isEmpty())
        {
            return 'No hay servicios con nombre o dominio.';
        }

        $groups = $usable->groupBy(function (Service $service): string
        {
            $name = trim((string) ($service->category?->name ?? ''));

            return $name !== '' ? $name : 'Otros';
        });

        $lines = [];
        foreach ($groups->keys()->sort()->values() as $category)
        {
            if ($lines !== [])
            {
                $lines[] = '';
            }

            $lines[] = (string) $category;
            foreach ($groups->get($category, collect()) as $service)
            {
                $lines[] = '- '.$this->serviceLine($service);
            }
        }

        return implode("\n", $lines);
    }

    public function line(ServiceSync $subscription, string $extra = ''): string
    {
        $name = trim($subscription->clientFacingName());
        if ($name === '')
        {
            $name = (string) $subscription->stripe_id;
        }

        $parts = [$name];
        $domain = $subscription->hostingDomainCandidate();
        if (is_string($domain) && $domain !== '' && ! str_contains(mb_strtolower($name), $domain))
        {
            $parts[] = $domain;
        }

        $parts[] = $this->statusLabel($subscription);

        $amount = (float) ($subscription->amount_total ?? 0);
        if ($amount > 0)
        {
            $parts[] = $this->money($amount, (string) $subscription->price_currency);
        }

        $frequency = trim($subscription->clientFrequencyLabel());
        if ($frequency !== '')
        {
            $parts[] = $frequency;
        }

        $end = $subscription->current_period_end;
        if ($end)
        {
            $parts[] = 'próxima '.Carbon::parse($end)->format('d/m/Y');
        }

        $extra = trim($extra);
        if ($extra !== '')
        {
            $parts[] = $extra;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  Collection<int, string>  $categories
     * @return list<string>
     */
    private function orderedCategories(Collection $categories): array
    {
        $preferred = ['Hosting', 'Dominios', 'Certificados', 'Email', 'Asesoramiento'];

        return $categories
            ->sortBy(function (string $name) use ($preferred): string
            {
                $index = array_search($name, $preferred, true);

                return sprintf('%02d-%s', $index === false ? 50 : $index, mb_strtolower($name));
            })
            ->values()
            ->all();
    }

    private function isCurrent(ServiceSync $subscription): bool
    {
        return in_array(strtolower(trim((string) $subscription->status)), [
            'active',
            'past_due',
            'trialing',
            'unpaid',
        ], true);
    }

    private function statusLabel(ServiceSync $subscription): string
    {
        return match (strtolower(trim((string) $subscription->status)))
        {
            'active' => 'activa',
            'past_due' => 'vencida',
            'trialing' => 'en prueba',
            'unpaid' => 'impaga',
            'paused' => 'pausada',
            'canceled', 'cancelled' => 'cancelada',
            'incomplete' => 'incompleta',
            'incomplete_expired' => 'expirada',
            default => trim((string) $subscription->status) !== '' ? (string) $subscription->status : 'sin estado',
        };
    }

    private function money(float $amount, string $currency): string
    {
        $label = number_format($amount, 2, ',', '.');
        $currency = strtoupper(trim($currency));

        return $currency !== '' ? $label.' '.$currency : $label;
    }

    private function serviceLabel(Service $service): string
    {
        $name = trim(html_entity_decode(strip_tags((string) $service->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $domain = trim((string) data_get($service->data, 'domain'));

        if ($name === '')
        {
            return $domain;
        }

        if ($domain !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($domain)))
        {
            return $name.' · '.$domain;
        }

        return $name;
    }

    private function serviceLine(Service $service): string
    {
        $parts = [$this->serviceLabel($service)];
        $price = (float) ($service->price ?? 0);
        if ($price > 0)
        {
            $symbol = trim((string) ($service->currency?->symbol ?: $service->currency?->code ?: ''));
            $parts[] = trim($symbol.' '.number_format($price, 2, ',', '.'));
        }

        if ($service->next_billing)
        {
            $parts[] = 'próxima '.Carbon::parse($service->next_billing)->format('d/m/Y');
        }

        return implode(' · ', $parts);
    }
}
