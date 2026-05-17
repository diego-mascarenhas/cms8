<?php

namespace App\Services\Billing;

use App\Enums\EmailPlan;
use App\Models\Team;
use App\Services\StripeAccountResolver;
use App\Services\TaxIdentifierService;
use App\Services\TeamStripeCustomerService;
use App\Support\StripeErrorMessage;
use Illuminate\Support\Facades\Log;

class TeamBillingDataService
{
    public function __construct(
        private TeamStripeCustomerService $customerService,
        private TaxIdentifierService $taxIdentifierService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Team $team): array
    {
        $team->resetProspectMonthlyLimitsIfNeeded();

        $mailerSubscription = $team->subscription('mailer');

        if ($mailerSubscription && $mailerSubscription->active())
        {
            $currentPlan = EmailPlan::fromStripePriceId($mailerSubscription->stripe_price);
        } else
        {
            $currentPlan = $team->getEmailPlan();
        }

        $billing = $this->fetchStripeBillingFields($team);

        return [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'email_plan' => [
                'key' => $currentPlan->value,
                'name' => $currentPlan->getDisplayName(),
                'description' => $currentPlan->getDescription(),
            ],
            'usage' => [
                'monthly_limit' => $currentPlan->getMonthlyLimit(),
                'monthly_used' => (int) $team->getSetting('email_monthly_used', 0),
                'daily_limit' => $currentPlan->getDailyLimit(),
                'daily_used' => (int) $team->getSetting('email_daily_used', 0),
                'contact_limit' => $currentPlan->getContactLimit(),
                'prospect_plan' => $team->getProspectPlan()->value,
                'prospect_credits_remaining' => $team->getRemainingProspectCredits(),
            ],
            'billing' => $billing,
            'mailer_subscription' => $mailerSubscription ? [
                'stripe_status' => $mailerSubscription->stripe_status,
                'active' => $mailerSubscription->active(),
                'ends_at' => $mailerSubscription->ends_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{success: bool, message: string, warning?: string}
     */
    public function updateBilling(Team $team, array $input): array
    {
        $taxIdNormalized = $this->taxIdentifierService->normalize((string) $input['tax_id']);

        try
        {
            $billingCustomerId = $this->customerService->getOrCreateStripeCustomerIdForCategory($team, 'mailer');
            if (! $billingCustomerId)
            {
                return [
                    'success' => false,
                    'message' => __('No se pudo crear el cliente de facturación.'),
                ];
            }

            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));

            $customerName = ! empty($input['business_name'])
                ? (string) $input['business_name']
                : (string) $input['individual_name'];
            $phone = $this->normalizePhoneNumber((string) $input['phone'], (string) $input['country']);
            $taxIdError = null;

            \Stripe\Customer::update($billingCustomerId, [
                'name' => $customerName,
                'phone' => $phone,
                'address' => [
                    'country' => (string) $input['country'],
                ],
            ]);

            try
            {
                $taxIds = \Stripe\Customer::allTaxIds($billingCustomerId, ['limit' => 100]);
                foreach ($taxIds->data as $taxId)
                {
                    \Stripe\Customer::deleteTaxId($billingCustomerId, $taxId->id);
                }

                $taxIdType = $this->taxIdentifierService->resolveStripeTaxIdType(
                    (string) $input['country'],
                    $taxIdNormalized,
                );

                if ($taxIdType !== null)
                {
                    \Stripe\Customer::createTaxId($billingCustomerId, [
                        'type' => $taxIdType,
                        'value' => $taxIdNormalized,
                    ]);
                }
            } catch (\Exception $e)
            {
                Log::error('Could not update tax ID', array_merge([
                    'customer' => $billingCustomerId,
                    'country' => $input['country'],
                    'tax_id' => $taxIdNormalized,
                ], StripeErrorMessage::logContext($e)));
                $taxIdError = StripeErrorMessage::display($e);
            }

            \Stripe\Customer::update($billingCustomerId, [
                'metadata' => [
                    'individual_name' => (string) $input['individual_name'],
                    'business_name' => (string) ($input['business_name'] ?? ''),
                    'tax_id' => $taxIdNormalized,
                    'country' => (string) $input['country'],
                ],
            ]);

            $message = __('Datos de facturación actualizados correctamente.');
            if ($taxIdError)
            {
                return [
                    'success' => true,
                    'message' => $message,
                    'warning' => __('Hubo un problema al actualizar el ID Fiscal: :error', ['error' => $taxIdError]),
                ];
            }

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Exception $e)
        {
            Log::error('Error updating billing data', StripeErrorMessage::logContext($e));

            return [
                'success' => false,
                'message' => __('Error al actualizar los datos de facturación: :error', [
                    'error' => StripeErrorMessage::display($e),
                ]),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchStripeBillingFields(Team $team): array
    {
        $defaults = [
            'has_stripe_customer' => false,
            'individual_name' => '',
            'business_name' => '',
            'country' => '',
            'phone' => '',
            'tax_id' => '',
        ];

        try
        {
            $customerId = $team->stripe_id ?: $this->customerService->getStripeCustomerIdForCategory($team, 'mailer');
            if (! $customerId)
            {
                return $defaults;
            }

            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));
            $customer = \Stripe\Customer::retrieve($customerId);

            $customerTeamId = $customer->metadata->team_id ?? null;
            if ($customerTeamId && (int) $customerTeamId !== (int) $team->id)
            {
                return $defaults;
            }

            $taxId = '';
            try
            {
                $taxIds = \Stripe\Customer::allTaxIds($customerId, ['limit' => 10]);
                if (count($taxIds->data) > 0)
                {
                    $taxId = $taxIds->data[0]->value ?? '';
                }
            } catch (\Exception $e)
            {
                Log::warning('Could not retrieve tax IDs: '.$e->getMessage());
            }

            return [
                'has_stripe_customer' => true,
                'individual_name' => (string) ($customer->metadata->individual_name
                    ?? $customer->collected_information->individual_name
                    ?? ''),
                'business_name' => (string) ($customer->metadata->business_name
                    ?? $customer->metadata->company_name
                    ?? $customer->collected_information->business_name
                    ?? ''),
                'country' => (string) ($customer->address->country ?? $customer->metadata->country ?? ''),
                'phone' => (string) ($customer->phone ?? ''),
                'tax_id' => $taxId,
            ];
        } catch (\Exception $e)
        {
            Log::error('Error fetching Stripe billing fields: '.$e->getMessage(), [
                'team_id' => $team->id,
            ]);

            return $defaults;
        }
    }

    public function normalizePhoneNumber(string $phone, string $country): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($cleaned, '+'))
        {
            return $cleaned;
        }

        $countryCode = match ($country)
        {
            'AR' => '+54',
            'ES' => '+34',
            'MX' => '+52',
            'US' => '+1',
            'CO' => '+57',
            'CL' => '+56',
            'PE' => '+51',
            'UY' => '+598',
            'BR' => '+55',
            default => '',
        };

        if ($country === 'AR' && ! str_starts_with($cleaned, '9'))
        {
            if (str_starts_with($cleaned, '15'))
            {
                $cleaned = substr($cleaned, 2);
            }
            $cleaned = '9'.$cleaned;
        }

        return $countryCode.$cleaned;
    }
}
