<?php

namespace App\Services;

use App\Models\Enterprise;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

class PaymentLinkAffiliateTeamAttributionService
{
    /**
     * After a public Payment Link checkout, persist {@see Team::$referred_by} (referrer Stripe customer id)
     * when the Checkout Session includes a referrer from either:
     * - a matching custom field ({@see config('humano_pricing.payment_link_affiliate_custom_field_keys')}), or
     * - {@see Session::$client_reference_id} (Payment Link URL query {@code client_reference_id}).
     * Custom field wins when both are set. Legacy numeric values resolve to a referrer enterprise id
     * and are stored as that enterprise team's {@see Team::$stripe_id}.
     */
    public function syncTeamReferrerFromSession(Team $team, Session $session): void
    {
        $referrerStripeId = $this->resolveReferrerStripeCustomerId($session);
        if ($referrerStripeId === null || $referrerStripeId === '')
        {
            return;
        }

        $referrerTeam = Team::findByStripeCustomerId($referrerStripeId);
        if (! $referrerTeam)
        {
            Log::info('Payment link affiliate: referrer team not found for stripe customer id', [
                'paying_team_id' => $team->id,
                'referrer_stripe_id' => $referrerStripeId,
            ]);

            return;
        }

        if ((int) $referrerTeam->id === (int) $team->id)
        {
            Log::info('Payment link affiliate: ignoring referrer on same team as payer', [
                'team_id' => $team->id,
            ]);

            return;
        }

        $existing = trim((string) ($team->referred_by ?? ''));
        if ($existing !== '' && strcasecmp($existing, $referrerStripeId) !== 0)
        {
            Log::info('Payment link affiliate: team already has referred_by, not overwriting', [
                'team_id' => $team->id,
                'existing' => $existing,
            ]);

            return;
        }

        if (strcasecmp($existing, $referrerStripeId) === 0)
        {
            return;
        }

        $team->forceFill(['referred_by' => $referrerStripeId])->save();

        Log::info('Payment link affiliate: set referred_by on paying team', [
            'team_id' => $team->id,
            'referrer_team_id' => $referrerTeam->id,
            'referrer_stripe_id' => $referrerStripeId,
        ]);
    }

    private function resolveReferrerStripeCustomerId(Session $session): ?string
    {
        $raw = $this->resolveReferrerRaw($session);
        if ($raw === null || $raw === '')
        {
            return null;
        }

        if (str_starts_with(strtolower($raw), 'cus_'))
        {
            return $raw;
        }

        if (ctype_digit($raw))
        {
            return $this->resolveStripeIdFromLegacyEnterpriseId((int) $raw);
        }

        Log::info('Payment link affiliate: referrer raw value is not cus_ or numeric enterprise id', [
            'value_preview' => substr($raw, 0, 32),
        ]);

        return null;
    }

    private function resolveReferrerRaw(Session $session): ?string
    {
        $fromCustom = $this->extractReferrerFromCustomFields($session);
        if ($fromCustom !== null && $fromCustom !== '')
        {
            return $fromCustom;
        }

        $fromClientRef = trim((string) ($session->client_reference_id ?? ''));
        if ($fromClientRef !== '')
        {
            return $fromClientRef;
        }

        return null;
    }

    private function resolveStripeIdFromLegacyEnterpriseId(int $enterpriseId): ?string
    {
        $referrerEnterprise = Enterprise::withoutGlobalScopes()
            ->where('type_id', 1)
            ->where('id', $enterpriseId)
            ->first();

        if (! $referrerEnterprise)
        {
            return null;
        }

        $referrerTeam = Team::query()->find($referrerEnterprise->team_id);
        if (! $referrerTeam)
        {
            return null;
        }

        $stripeId = trim((string) ($referrerTeam->stripe_id ?? ''));
        if ($stripeId === '')
        {
            Log::info('Payment link affiliate: legacy enterprise referrer team has no stripe_id', [
                'referrer_enterprise_id' => $enterpriseId,
                'referrer_team_id' => $referrerTeam->id,
            ]);

            return null;
        }

        return $stripeId;
    }

    private function extractReferrerFromCustomFields(Session $session): ?string
    {
        $keys = config('humano_pricing.payment_link_affiliate_custom_field_keys', ['referente', 'affiliate']);
        if (! is_array($keys) || $keys === [])
        {
            return null;
        }

        $normalizedKeys = [];
        foreach ($keys as $key)
        {
            $k = strtolower(trim((string) $key));
            if ($k !== '')
            {
                $normalizedKeys[] = $k;
            }
        }

        if ($normalizedKeys === [])
        {
            return null;
        }

        $fields = $session->custom_fields ?? null;
        if ($fields === null)
        {
            return null;
        }

        foreach ($fields as $field)
        {
            $fieldKey = strtolower(trim((string) $this->readStripeChildString($field, 'key')));
            if ($fieldKey === '' || ! in_array($fieldKey, $normalizedKeys, true))
            {
                continue;
            }

            $type = strtolower(trim((string) $this->readStripeChildString($field, 'type')));
            $value = match ($type)
            {
                'numeric' => trim((string) $this->readStripeChildString($this->readStripeChild($field, 'numeric'), 'value')),
                'text' => trim((string) $this->readStripeChildString($this->readStripeChild($field, 'text'), 'value')),
                default => '',
            };

            if ($value !== '')
            {
                return $value;
            }
        }

        return null;
    }

    private function readStripeChild(mixed $parent, string $childKey): mixed
    {
        if (is_array($parent))
        {
            return $parent[$childKey] ?? null;
        }
        if (is_object($parent) && isset($parent->{$childKey}))
        {
            return $parent->{$childKey};
        }

        return null;
    }

    private function readStripeChildString(mixed $parent, string $childKey): string
    {
        $v = $this->readStripeChild($parent, $childKey);
        if (is_string($v))
        {
            return $v;
        }
        if (is_scalar($v))
        {
            return (string) $v;
        }

        return '';
    }
}
