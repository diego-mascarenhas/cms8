<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'code',
        'address',
        'data',
        'status',
        'is_main',
    ];

    protected $casts = [
        'data' => 'array',
        'status' => 'boolean',
        'is_main' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Ensure the team has a default main branch (code MAIN). Other stores lose is_main.
     */
    public static function ensureMainStoreForTeam(int $teamId): self
    {
        $main = static::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'MAIN',
            ],
            [
                'name' => 'Principal',
                'address' => null,
                'status' => true,
                'is_main' => true,
            ],
        );

        static::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('id', '!=', $main->id)
            ->update(['is_main' => false]);

        return $main;
    }

    public const CHECKOUT_PAYMENT_CASH = 'cash';

    public const CHECKOUT_PAYMENT_BANK_TRANSFER = 'bank_transfer';

    public const CHECKOUT_PAYMENT_CARD = 'card';

    public const CHECKOUT_PAYMENT_MERCADOPAGO = 'mercadopago';

    public const CHECKOUT_PAYMENT_QR = 'qr';

    public const CHECKOUT_PAYMENT_PAYPAL = 'paypal';

    public const CHECKOUT_PAYMENT_BIZUM = 'bizum';

    public const CHECKOUT_FULFILLMENT_PICKUP = 'pickup';

    public const CHECKOUT_FULFILLMENT_DELIVERY = 'delivery';

    /**
     * @return list<string>
     */
    public static function checkoutPaymentMethodKeys(): array
    {
        return [
            self::CHECKOUT_PAYMENT_CASH,
            self::CHECKOUT_PAYMENT_BANK_TRANSFER,
            self::CHECKOUT_PAYMENT_CARD,
            self::CHECKOUT_PAYMENT_MERCADOPAGO,
            self::CHECKOUT_PAYMENT_QR,
            self::CHECKOUT_PAYMENT_PAYPAL,
            self::CHECKOUT_PAYMENT_BIZUM,
        ];
    }

    /**
     * @return list<string>
     */
    public static function checkoutFulfillmentKeys(): array
    {
        return [
            self::CHECKOUT_FULFILLMENT_PICKUP,
            self::CHECKOUT_FULFILLMENT_DELIVERY,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function checkoutPaymentMethodLabels(): array
    {
        return [
            self::CHECKOUT_PAYMENT_CASH => __('Efectivo'),
            self::CHECKOUT_PAYMENT_BANK_TRANSFER => __('Transferencia bancaria'),
            self::CHECKOUT_PAYMENT_CARD => __('Tarjeta (débito / crédito)'),
            self::CHECKOUT_PAYMENT_MERCADOPAGO => __('Mercado Pago'),
            self::CHECKOUT_PAYMENT_QR => __('QR / billetera'),
            self::CHECKOUT_PAYMENT_PAYPAL => __('PayPal'),
            self::CHECKOUT_PAYMENT_BIZUM => __('Bizum'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function checkoutFulfillmentLabels(): array
    {
        return [
            self::CHECKOUT_FULFILLMENT_PICKUP => __('Retiro en el local'),
            self::CHECKOUT_FULFILLMENT_DELIVERY => __('Envío a domicilio'),
        ];
    }

    /**
     * Payment methods this branch accepts for in-store / WhatsApp sales.
     * If unset in {@see $data}, all known methods are treated as enabled (backward compatible).
     *
     * @return list<string>
     */
    public function enabledCheckoutPaymentMethods(): array
    {
        $saved = data_get($this->data, 'checkout.payment_methods');
        if (! is_array($saved) || $saved === [])
        {
            return self::checkoutPaymentMethodKeys();
        }

        $allowed = self::checkoutPaymentMethodKeys();

        return array_values(array_intersect($allowed, $saved));
    }

    /**
     * How customers may receive the order from this branch.
     *
     * @return list<string>
     */
    public function enabledCheckoutFulfillmentTypes(): array
    {
        $saved = data_get($this->data, 'checkout.fulfillment_types');
        if (! is_array($saved) || $saved === [])
        {
            return self::checkoutFulfillmentKeys();
        }

        $allowed = self::checkoutFulfillmentKeys();

        return array_values(array_intersect($allowed, $saved));
    }

    /**
     * @return list<string>
     */
    public static function weekdayKeys(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    /**
     * Merge checkout and branch profile fields into the JSON `data` column.
     *
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function operationalData(?array $existing, array $validated): array
    {
        $data = is_array($existing) ? $existing : [];
        $data['checkout'] = [
            'payment_methods' => array_values(array_intersect(
                self::checkoutPaymentMethodKeys(),
                $validated['checkout_payment_methods'] ?? [],
            )),
            'fulfillment_types' => array_values(array_intersect(
                self::checkoutFulfillmentKeys(),
                $validated['checkout_fulfillment_types'] ?? [],
            )),
        ];

        foreach (['phone', 'whatsapp', 'notes', 'maps_url'] as $key)
        {
            if (array_key_exists($key, $validated))
            {
                $data[$key] = self::nullableString($validated[$key] ?? null);
            }
        }

        if (array_key_exists('hours', $validated))
        {
            $data['hours'] = self::normalizeHours($validated['hours'] ?? null);
        }

        if (array_key_exists('delivery_area', $validated)
            || array_key_exists('delivery_notes', $validated)
            || array_key_exists('delivery_cost', $validated))
        {
            $delivery = is_array($data['delivery'] ?? null) ? $data['delivery'] : [];
            if (array_key_exists('delivery_area', $validated))
            {
                $delivery['area'] = self::nullableString($validated['delivery_area'] ?? null);
            }
            if (array_key_exists('delivery_notes', $validated))
            {
                $delivery['notes'] = self::nullableString($validated['delivery_notes'] ?? null);
            }
            if (array_key_exists('delivery_cost', $validated))
            {
                $cost = $validated['delivery_cost'];
                $delivery['cost'] = $cost === null || $cost === '' ? null : (float) $cost;
            }
            $data['delivery'] = $delivery;
        }

        return $data;
    }

    /**
     * @return list<array{day: string, open: string|null, close: string|null, afternoon_open: string|null, afternoon_close: string|null, closed: bool}>
     */
    public function openingHours(): array
    {
        return self::normalizeHours(data_get($this->data, 'hours'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function attributesFromValidated(array $validated, ?self $existing): array
    {
        $data = self::operationalData($existing?->data, $validated);
        unset(
            $validated['checkout_payment_methods'],
            $validated['checkout_fulfillment_types'],
            $validated['phone'],
            $validated['whatsapp'],
            $validated['hours'],
            $validated['notes'],
            $validated['maps_url'],
            $validated['delivery_area'],
            $validated['delivery_notes'],
            $validated['delivery_cost'],
        );
        $validated['data'] = $data;

        return $validated;
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return list<array{day: string, open: string|null, close: string|null, afternoon_open: string|null, afternoon_close: string|null, closed: bool}>
     */
    private static function normalizeHours(mixed $hours): array
    {
        $byDay = [];
        if (is_array($hours))
        {
            foreach ($hours as $row)
            {
                if (! is_array($row))
                {
                    continue;
                }
                $day = (string) ($row['day'] ?? '');
                if (! in_array($day, self::weekdayKeys(), true))
                {
                    continue;
                }
                $closed = (bool) ($row['closed'] ?? false);
                $byDay[$day] = [
                    'day' => $day,
                    'open' => $closed ? null : self::nullableString($row['open'] ?? null),
                    'close' => $closed ? null : self::nullableString($row['close'] ?? null),
                    'afternoon_open' => $closed ? null : self::nullableString($row['afternoon_open'] ?? null),
                    'afternoon_close' => $closed ? null : self::nullableString($row['afternoon_close'] ?? null),
                    'closed' => $closed,
                ];
            }
        }

        return array_map(function (string $day) use ($byDay): array
        {
            return $byDay[$day] ?? [
                'day' => $day,
                'open' => in_array($day, ['sat', 'sun'], true) ? ($day === 'sat' ? '09:00' : null) : '09:00',
                'close' => in_array($day, ['sat', 'sun'], true) ? ($day === 'sat' ? '13:00' : null) : '18:00',
                'afternoon_open' => null,
                'afternoon_close' => null,
                'closed' => $day === 'sun',
            ];
        }, self::weekdayKeys());
    }
}
