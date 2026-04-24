<?php

namespace App\Support\Payments;

use Illuminate\Support\Str;

/**
 * Human-readable method label for Stripe charge payloads (payment_method_details),
 * aligned with other payment type names in the app (e.g. Credit Card, Bank Transfer).
 */
class StripePaymentMethodLabel
{
    public static function fromChargePayload(?array $charge): string
    {
        if (! is_array($charge) || $charge === [])
        {
            return '';
        }

        $details = $charge['payment_method_details'] ?? null;
        if (is_array($details))
        {
            $type = (string) ($details['type'] ?? '');
            if ($type !== '')
            {
                $label = self::fromPaymentMethodDetailsType($type, $details);
                if ($label !== '')
                {
                    return $label;
                }
            }
        }

        if (! empty($charge['source']) && is_array($charge['source']))
        {
            $sType = (string) ($charge['source']['type'] ?? '');
            if ($sType === 'sepa_debit' || $sType === 'ach_credit_transfer' || $sType === 'ach_debit')
            {
                return 'Bank Transfer';
            }
        }

        return '';
    }

    private static function fromPaymentMethodDetailsType(string $type, array $details): string
    {
        return match ($type)
        {
            'card' => self::cardLabel($details['card'] ?? null),
            'sepa_debit' => 'Bank Transfer',
            'acss_debit' => 'Bank Transfer',
            'us_bank_account' => 'Bank Transfer',
            'ach_debit' => 'ACH',
            'ach_credit_transfer' => 'Bank Transfer',
            'bancontact' => 'Bancontact',
            'eps' => 'EPS',
            'fpx' => 'FPX',
            'giropay' => 'Giropay',
            'ideal' => 'iDEAL',
            'link' => 'Link',
            'p24' => 'P24',
            'sofort' => 'Sofort',
            'sepa_credit_transfer' => 'Bank Transfer',
            'bacs_debit' => 'Bacs Direct Debit',
            'boleto' => 'Boleto',
            'oxxo' => 'Oxxo',
            'customer_balance' => 'Customer balance',
            'alipay' => 'Alipay',
            'au_becs_debit' => 'BECS Direct Debit',
            'blik' => 'BLIK',
            'afterpay_clearpay' => 'Afterpay / Clearpay',
            'cashapp' => 'Cash App',
            'kakao_pay' => 'Kakao Pay',
            'klarna' => 'Klarna',
            'mobilepay' => 'MobilePay',
            'paypal' => 'PayPal',
            'revolut_pay' => 'Revolut Pay',
            'samsung_pay' => 'Samsung Pay',
            'swish' => 'Swish',
            'upi' => 'UPI',
            'wechat' => 'WeChat Pay',
            'affirm' => 'Affirm',
            'zip' => 'Zip',
            default => Str::of($type)->replace('_', ' ')->headline()->toString(),
        };
    }

    private static function cardLabel(mixed $card): string
    {
        if (is_array($card))
        {
            $brand = isset($card['brand']) ? Str::of((string) $card['brand'])->headline()->toString() : '';

            return $brand !== '' ? "Credit Card ({$brand})" : 'Credit Card';
        }

        return 'Credit Card';
    }
}
