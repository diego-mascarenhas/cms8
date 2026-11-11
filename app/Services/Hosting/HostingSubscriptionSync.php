<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Models\ServiceSync;
use App\Models\Team;
use RuntimeException;

class HostingSubscriptionSync
{
    public function __construct(private readonly StripeHostingPlanGateway $stripe) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    public function strings(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value)
        {
            if (! is_string($key) || $key === '')
            {
                continue;
            }

            if (is_bool($value))
            {
                $clean[$key] = $value ? 'true' : 'false';

                continue;
            }

            if (is_scalar($value) || $value === null)
            {
                $clean[$key] = trim((string) $value);
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    public function remember(ServiceSync $sync, array $metadata): void
    {
        $data = is_array($sync->data) ? $sync->data : [];
        $data['metadata'] = $metadata;

        foreach (['category', 'domain', 'type', 'plan'] as $key)
        {
            if (array_key_exists($key, $metadata))
            {
                $data[$key] = $metadata[$key];
            }
        }

        $raw = is_array($sync->raw_payload) ? $sync->raw_payload : [];
        $raw['metadata'] = $metadata;

        $sync->forceFill([
            'data' => $data,
            'raw_payload' => $raw,
        ])->save();
    }

    /**
     * @param  array<string, string>  $next
     * @param  array<string, mixed>  $previous
     * @return array<string, string>
     */
    public function replacement(array $next, array $previous): array
    {
        $payload = $next;

        foreach ($previous as $key => $value)
        {
            if (! is_string($key) || array_key_exists($key, $payload))
            {
                continue;
            }

            $payload[$key] = '';
        }

        return $payload;
    }

    public function afterPlanChange(Team $team, Domain $domain): string
    {
        $sync = $this->subscriptionFor($team, $domain);
        if ($sync === null)
        {
            return '';
        }

        $metadata = $this->strings(is_array(data_get($sync->data, 'metadata')) ? data_get($sync->data, 'metadata') : []);
        $metadata['type'] = 'hosting';
        $metadata['category'] = 'hosting';
        $metadata['domain'] = strtolower((string) $domain->domain);
        $metadata['plan'] = (string) $domain->plan;

        $swapped = false;

        try
        {
            $current = $this->stripe->currentItem($team, (string) $sync->stripe_id);
            $target = $current === null ? null : $this->matchingPrice($team, $current, (string) $domain->plan);

            if ($current !== null && $target !== null && $target['price_id'] !== $current['price_id'])
            {
                $this->stripe->swap($team, (string) $sync->stripe_id, $current['item_id'], $target['price_id']);
                $sync->forceFill([
                    'plan_name' => $target['product_name'],
                    'unit_amount' => round($target['unit_amount'] / 100, 2),
                    'amount_total' => round($target['unit_amount'] / 100, 2),
                    'price_currency' => strtoupper($target['currency']),
                    'plan_interval' => $target['interval'],
                    'plan_interval_count' => $target['interval_count'],
                ])->save();
                $swapped = true;
            }

            $written = $this->stripe->replaceMetadata(
                $team,
                (string) $sync->stripe_id,
                $this->replacement($metadata, $this->strings(is_array(data_get($sync->raw_payload, 'metadata')) ? data_get($sync->raw_payload, 'metadata') : [])),
            );
        } catch (RuntimeException $exception)
        {
            return 'El servidor cambió, pero Stripe no: '.$exception->getMessage();
        }

        $this->remember($sync->fresh() ?? $sync, $metadata);

        if (! $written)
        {
            return 'Guardé el plan en la suscripción local. Stripe del equipo no está configurado.';
        }

        return $swapped
            ? 'La suscripción de Stripe quedó en el nuevo plan.'
            : 'Actualicé la metadata de la suscripción.';
    }

    private function subscriptionFor(Team $team, Domain $domain): ?ServiceSync
    {
        $needle = strtolower((string) $domain->domain);

        return ServiceSync::query()
            ->where('team_id', $team->id)
            ->orderByDesc('id')
            ->get()
            ->first(function (ServiceSync $sync) use ($needle): bool
            {
                return $sync->isClientHosting() && $sync->hostingDomainCandidate() === $needle;
            });
    }

    /**
     * @param  array{item_id: string, price_id: string, currency: string, interval: string, interval_count: int}  $current
     * @return array{price_id: string, product_name: string, plan: string, currency: string, interval: string, interval_count: int, unit_amount: int, whm_package: string|null}|null
     */
    private function matchingPrice(Team $team, array $current, string $package): ?array
    {
        $target = strtolower(trim($package));
        if ($target === '')
        {
            return null;
        }

        foreach ($this->stripe->catalog($team) as $price)
        {
            if ($price['currency'] !== $current['currency'] || $price['interval'] !== $current['interval'] || $price['interval_count'] !== $current['interval_count'])
            {
                continue;
            }

            $names = array_filter([
                strtolower((string) ($price['whm_package'] ?? '')),
                strtolower($price['plan']),
            ]);

            if (in_array($target, $names, true))
            {
                return $price;
            }
        }

        return null;
    }
}
