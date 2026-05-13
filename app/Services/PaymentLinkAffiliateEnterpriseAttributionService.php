<?php

namespace App\Services;

use App\Models\Enterprise;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

class PaymentLinkAffiliateEnterpriseAttributionService
{
    /**
     * After a public Payment Link checkout, persist {@see Enterprise::$referred_by} on the paying team's
     * billing client enterprise (type_id = client, code = Stripe customer id) when the Checkout Session
     * includes a referrer enterprise id (digits only) from either:
     * - a matching custom field ({@see config('humano_pricing.payment_link_affiliate_custom_field_keys')}), or
     * - {@see Session::$client_reference_id} (Payment Link URL query {@code client_reference_id}).
     * Custom field wins when both are set. This feeds {@see AffiliateCommissionRecorder} on invoice payment.
     */
    public function syncBillingEnterpriseReferrerFromSession(Team $team, Session $session, int $actingUserId): void
    {
        $rawId = $this->resolveReferrerEnterpriseIdRaw($session);
        if ($rawId === null || $rawId === '')
        {
            return;
        }

        if (! ctype_digit($rawId))
        {
            Log::info('Payment link affiliate: referrer raw value is not a numeric enterprise id', [
                'team_id' => $team->id,
                'value_preview' => substr($rawId, 0, 32),
            ]);

            return;
        }

        $referrerEnterprise = Enterprise::withoutGlobalScopes()
            ->where('type_id', 1)
            ->where('id', (int) $rawId)
            ->first();

        if (! $referrerEnterprise)
        {
            Log::info('Payment link affiliate: referrer enterprise not found for id', [
                'team_id' => $team->id,
                'referrer_enterprise_id' => $rawId,
            ]);

            return;
        }

        if ((int) $referrerEnterprise->team_id === (int) $team->id)
        {
            Log::info('Payment link affiliate: ignoring referrer on same team as payer', [
                'team_id' => $team->id,
                'referrer_enterprise_id' => $referrerEnterprise->id,
            ]);

            return;
        }

        $customerId = $this->stripeCustomerIdForTeam($team);
        if ($customerId === null || $customerId === '')
        {
            Log::warning('Payment link affiliate: team has no Stripe customer id for billing enterprise', [
                'team_id' => $team->id,
            ]);

            return;
        }

        $billingEnterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('type_id', 1)
            ->where('code', $customerId)
            ->first();

        $referredBy = (string) $referrerEnterprise->id;

        if ($billingEnterprise)
        {
            $existing = trim((string) ($billingEnterprise->referred_by ?? ''));
            if ($existing !== '' && strcasecmp($existing, $referredBy) !== 0)
            {
                Log::info('Payment link affiliate: billing enterprise already has referred_by, not overwriting', [
                    'team_id' => $team->id,
                    'enterprise_id' => $billingEnterprise->id,
                ]);

                return;
            }

            if (strcasecmp($existing, $referredBy) === 0)
            {
                return;
            }

            $billingEnterprise->forceFill(['referred_by' => $referredBy])->save();

            Log::info('Payment link affiliate: set referred_by on existing billing enterprise', [
                'team_id' => $team->id,
                'enterprise_id' => $billingEnterprise->id,
                'referrer_enterprise_id' => $referrerEnterprise->id,
            ]);

            return;
        }

        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'name' => $team->name,
            'code' => $customerId,
            'referred_by' => $referredBy,
            'email' => $team->owner?->email,
            'status_id' => 1,
            'creator_id' => $actingUserId,
            'responsible_id' => $actingUserId,
        ]);

        Log::info('Payment link affiliate: created billing enterprise with referred_by', [
            'team_id' => $team->id,
            'stripe_customer_id' => $customerId,
            'referrer_enterprise_id' => $referrerEnterprise->id,
        ]);
    }

    private function stripeCustomerIdForTeam(Team $team): ?string
    {
        $id = trim((string) ($team->stripe_id ?? ''));
        if ($id !== '')
        {
            return $id;
        }

        return null;
    }

    /**
     * Prefer custom checkout field; otherwise use Stripe {@see Session::$client_reference_id} from the Payment Link URL.
     */
    private function resolveReferrerEnterpriseIdRaw(Session $session): ?string
    {
        $fromCustom = $this->extractReferrerEnterpriseIdFromCustomFields($session);
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

    private function extractReferrerEnterpriseIdFromCustomFields(Session $session): ?string
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
