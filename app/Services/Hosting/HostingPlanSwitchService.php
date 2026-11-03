<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Services\ControlPanel\ControlPanelManager;
use RuntimeException;

class HostingPlanSwitchService
{
    public function __construct(
        private readonly StripeHostingPlanGateway $stripePlans,
        private readonly ControlPanelManager $controlPanelManager,
    ) {}

    public function apply(Team $team, Domain $domain, ServiceSync $subscription, ?string $requestedPlan): string
    {
        $domain->loadMissing('server');
        $server = $domain->server;

        if ($server === null || $server->control_panel !== 'cpanel' || ! $server->hasToken())
        {
            return 'El dominio '.$domain->domain.' no está en un cPanel con credenciales. No cambié el plan ni la suscripción.';
        }

        $current = $this->stripePlans->currentItem($team, (string) $subscription->stripe_id);
        if ($current === null)
        {
            return 'No pude leer la suscripción de Stripe de '.$domain->domain.'. No cambié el plan.';
        }

        $options = $this->optionsFor($team, $domain, $current);
        if ($options === [])
        {
            return 'No hay un plan de Stripe, en la misma moneda e intervalo, que tenga paquete en este servidor. No cambié nada.';
        }

        $requested = trim((string) $requestedPlan);
        if ($requested === '')
        {
            return $this->listOptions($domain, $options);
        }

        $matches = $this->matchOptions($options, $requested);
        if (count($matches) !== 1)
        {
            return $this->listOptions(
                $domain,
                $options,
                count($matches) > 1
                    ? 'Hay más de un plan que coincide con "'.$requested.'".'
                    : 'No reconocí el plan "'.$requested.'".',
            );
        }

        $target = $matches[0];
        $stripeAlready = $target['price_id'] === $current['price_id'];
        $whmAlready = $target['whm_package'] === (string) $domain->plan;
        if ($stripeAlready && $whmAlready)
        {
            return $domain->domain.' ya está en '.$target['product_name'].' ('.$target['whm_package'].'). No cambié nada.';
        }

        if (! $stripeAlready)
        {
            try
            {
                $this->stripePlans->swap($team, (string) $subscription->stripe_id, $current['item_id'], $target['price_id']);
            } catch (RuntimeException $exception)
            {
                return 'Stripe no cambió la suscripción de '.$domain->domain.'. No toqué el servidor. '.$exception->getMessage();
            }
        }

        if (! $whmAlready)
        {
            $result = $this->controlPanelManager
                ->forServer($server)
                ->changePlan($server, $domain, $target['whm_package']);

            if (! ($result['success'] ?? false))
            {
                $error = (string) ($result['error'] ?? 'El servidor rechazó el plan.');

                if ($stripeAlready)
                {
                    return 'No cambié el plan del servidor de '.$domain->domain.'. '.$error;
                }

                try
                {
                    $this->stripePlans->swap($team, (string) $subscription->stripe_id, $current['item_id'], $current['price_id']);
                } catch (RuntimeException $exception)
                {
                    return 'La suscripción de Stripe de '.$domain->domain.' pasó a '.$target['product_name']
                        .', pero el servidor sigue en '.($domain->plan ?: 'el plan anterior').'. '.$error
                        .' Tampoco pude volver el precio atrás: '.$exception->getMessage();
                }

                return 'No cambié el plan. El servidor rechazó '.$target['whm_package'].' y la suscripción volvió al precio anterior. '.$error;
            }
        }

        $subscription->forceFill([
            'plan_name' => $target['product_name'],
            'unit_amount' => round($target['unit_amount'] / 100, 2),
            'price_currency' => strtoupper($target['currency']),
            'plan_interval' => $target['interval'],
            'plan_interval_count' => $target['interval_count'],
        ])->save();

        return 'Listo. '.$domain->domain.' ahora está en '.$target['product_name']
            .' ('.$this->money($target).'). El servidor quedó en el paquete '.$target['whm_package'].'.';
    }

    /**
     * @param  array{item_id: string, price_id: string, currency: string, interval: string, interval_count: int}  $current
     * @return list<array{
     *     price_id: string,
     *     product_name: string,
     *     plan: string,
     *     currency: string,
     *     interval: string,
     *     interval_count: int,
     *     unit_amount: int,
     *     whm_package: string
     * }>
     */
    private function optionsFor(Team $team, Domain $domain, array $current): array
    {
        $packages = $this->packages($domain);
        $options = [];

        foreach ($this->stripePlans->catalog($team) as $price)
        {
            if ($price['currency'] !== $current['currency'] || $price['interval'] !== $current['interval'] || $price['interval_count'] !== $current['interval_count'])
            {
                continue;
            }

            $package = $this->packageFor($price, $packages);
            if ($package === null)
            {
                continue;
            }

            $price['whm_package'] = $package;
            $options[] = $price;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function packages(Domain $domain): array
    {
        $cached = $domain->getCachedAvailablePlans();
        if ($cached !== [])
        {
            return array_values($cached);
        }

        $server = $domain->server;
        if ($server === null || ! $this->controlPanelManager->supports($server))
        {
            return [];
        }

        $listed = $this->controlPanelManager->forServer($server)->listPlans($server);

        return array_values(array_filter(
            $listed['plans'] ?? [],
            fn ($plan): bool => is_string($plan) && $plan !== '',
        ));
    }

    /**
     * @param  array{plan: string, whm_package: string|null}  $price
     * @param  list<string>  $packages
     */
    private function packageFor(array $price, array $packages): ?string
    {
        $explicit = trim((string) ($price['whm_package'] ?? ''));
        if ($explicit !== '' && in_array($explicit, $packages, true))
        {
            return $explicit;
        }

        $slug = strtolower(trim($price['plan']));
        if (! preg_match('/^(hosting|cloud|vps)-[a-z0-9]+$/', $slug))
        {
            return null;
        }

        $suffix = substr($slug, strrpos($slug, '-') + 1);
        $candidate = 'revision_'.$suffix;
        $found = array_values(array_filter(
            $packages,
            fn (string $package): bool => strtolower($package) === $candidate,
        ));

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * @param  list<array{price_id: string, product_name: string, plan: string, whm_package: string, unit_amount: int, currency: string, interval: string}>  $options
     * @return list<array{price_id: string, product_name: string, plan: string, whm_package: string, unit_amount: int, currency: string, interval: string}>
     */
    private function matchOptions(array $options, string $requested): array
    {
        $needle = $this->normalize($requested);
        if (mb_strlen($needle) < 3)
        {
            return [];
        }

        return array_values(array_filter($options, function (array $option) use ($needle): bool
        {
            foreach ([$option['plan'], $option['product_name'], $option['whm_package']] as $label)
            {
                $normalized = $this->normalize($label);
                if ($normalized === $needle || str_contains($normalized, $needle) || str_contains($needle, $normalized))
                {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  list<array{product_name: string, whm_package: string, unit_amount: int, currency: string, interval: string}>  $options
     */
    private function listOptions(Domain $domain, array $options, ?string $prefix = null): string
    {
        $lines = array_filter([
            $prefix,
            'No cambié el plan de '.$domain->domain.'. Para cambiarlo, el cliente tiene que elegir uno de estos. El precio es el de Stripe y el paquete es el del servidor:',
        ]);

        foreach ($options as $option)
        {
            $lines[] = '- '.$option['product_name'].' · '.$option['whm_package'].' · '.$this->money($option);
        }

        $lines[] = 'Cuando elija, llamá de nuevo con ese nombre en plan.';

        return implode("\n", $lines);
    }

    /**
     * @param  array{unit_amount: int, currency: string, interval: string}  $option
     */
    private function money(array $option): string
    {
        $amount = number_format($option['unit_amount'] / 100, 2, ',', '.');
        $period = match ($option['interval'])
        {
            'year' => 'al año',
            'week' => 'a la semana',
            'day' => 'al día',
            default => 'al mes',
        };

        return $amount.' '.strtoupper($option['currency']).' '.$period;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
