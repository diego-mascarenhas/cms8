<?php

namespace App\Models;

use App\Enums\TeamBillingProduct;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamBillingRate extends Model
{
    /** @use HasFactory<\Database\Factories\TeamBillingRateFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'product',
        'amount',
        'currency',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'product' => TeamBillingProduct::class,
        'amount' => 'decimal:6',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function amountOn(?int $teamId, TeamBillingProduct $product, DateTimeInterface|string|null $on = null): float
    {
        $at = self::resolveDate($on);
        $row = self::inEffect($teamId, $product, $at)
            ?? ($teamId !== null ? self::inEffect(null, $product, $at) : null);

        if ($row)
        {
            return self::normalizeAmount($product, (float) $row->amount);
        }

        return self::configAmount($teamId, $product);
    }

    public static function formattedAmountOn(?int $teamId, TeamBillingProduct $product, DateTimeInterface|string|null $on = null): string
    {
        return self::formatAmount(self::amountOn($teamId, $product, $on));
    }

    public static function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 6, '.', ''), '0'), '.') ?: '0';
    }

    public function formattedAmount(): string
    {
        return self::formatAmount((float) $this->amount);
    }

    /**
     * @return list<array{from: Carbon, to: Carbon, amount: float}>
     */
    public static function windows(?int $teamId, TeamBillingProduct $product, Carbon $from, Carbon $to): array
    {
        if ($to->lte($from))
        {
            return [[
                'from' => $from->copy(),
                'to' => $to->copy(),
                'amount' => self::amountOn($teamId, $product, $from),
            ]];
        }

        $rows = self::query()
            ->where('product', $product->value)
            ->where(function ($query) use ($teamId): void
            {
                $query->where('team_id', $teamId);
                if ($teamId !== null)
                {
                    $query->orWhereNull('team_id');
                }
            })
            ->where('effective_from', '<', $to)
            ->where(function ($query) use ($from): void
            {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $from);
            })
            ->orderByDesc('team_id')
            ->orderBy('effective_from')
            ->get();

        $teamRows = $rows->where('team_id', $teamId)->values();
        $applicable = $teamRows->isNotEmpty() ? $teamRows : $rows->whereNull('team_id')->values();

        if ($applicable->isEmpty())
        {
            return [[
                'from' => $from->copy(),
                'to' => $to->copy(),
                'amount' => self::configAmount($teamId, $product),
            ]];
        }

        $windows = [];
        $cursor = $from->copy();
        foreach ($applicable as $row)
        {
            $start = Carbon::parse($row->effective_from)->max($from);
            $end = $row->effective_to ? Carbon::parse($row->effective_to)->min($to) : $to->copy();
            if ($end->lte($cursor) || $end->lte($start))
            {
                continue;
            }
            if ($start->gt($cursor))
            {
                $windows[] = [
                    'from' => $cursor->copy(),
                    'to' => $start->copy(),
                    'amount' => self::configAmount($teamId, $product),
                ];
            }
            $windows[] = [
                'from' => $start->copy(),
                'to' => $end->copy(),
                'amount' => self::normalizeAmount($product, (float) $row->amount),
            ];
            $cursor = $end->copy();
        }

        if ($cursor->lt($to))
        {
            $windows[] = [
                'from' => $cursor->copy(),
                'to' => $to->copy(),
                'amount' => self::amountOn($teamId, $product, $cursor),
            ];
        }

        return $windows !== [] ? $windows : [[
            'from' => $from->copy(),
            'to' => $to->copy(),
            'amount' => self::amountOn($teamId, $product, $from),
        ]];
    }

    public static function setAmount(
        ?int $teamId,
        TeamBillingProduct $product,
        float $amount,
        DateTimeInterface|string|null $from = null,
        ?string $currency = null,
    ): self {
        $start = self::resolveDate($from);
        $amount = self::normalizeAmount($product, $amount);
        $open = self::query()
            ->where('team_id', $teamId)
            ->where('product', $product->value)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();

        if ($open && abs((float) $open->amount - $amount) < 0.0000001)
        {
            return $open;
        }

        if ($open)
        {
            $open->forceFill(['effective_to' => $start])->save();
        } else
        {
            $previous = self::configAmount($teamId, $product);
            if (abs($previous - $amount) > 0.0000001)
            {
                $createdAt = $teamId ? Team::query()->find($teamId)?->created_at?->copy() : null;
                $seedFrom = $createdAt && $createdAt->lt($start)
                    ? $createdAt
                    : $start->copy()->subSecond();
                self::query()->create([
                    'team_id' => $teamId,
                    'product' => $product,
                    'amount' => $previous,
                    'currency' => $currency ?? self::defaultCurrency($product),
                    'effective_from' => $seedFrom,
                    'effective_to' => $start,
                ]);
            }
        }

        return self::query()->create([
            'team_id' => $teamId,
            'product' => $product,
            'amount' => $amount,
            'currency' => $currency ?? self::defaultCurrency($product),
            'effective_from' => $start,
            'effective_to' => null,
        ]);
    }

    public static function configAmount(?int $teamId, TeamBillingProduct $product): float
    {
        $overrides = self::configOverrides($product);
        if ($teamId !== null)
        {
            if (array_key_exists($teamId, $overrides))
            {
                return self::normalizeAmount($product, (float) $overrides[$teamId]);
            }
            if (array_key_exists((string) $teamId, $overrides))
            {
                return self::normalizeAmount($product, (float) $overrides[(string) $teamId]);
            }
        }

        return match ($product)
        {
            TeamBillingProduct::TokensMultiplier => max(1, (float) config('humano_pricing.token_billing.client_token_multiplier', 10)),
            TeamBillingProduct::WhatsappSend => max(0, (float) config('humano_pricing.whatsapp_message_billing.our_amount', 0.003)),
            TeamBillingProduct::MailerSend => max(0, (float) config('emailer.payg.price_per_email', 0.01)),
        };
    }

    /**
     * @return array<int|string, float|int|string>
     */
    private static function configOverrides(TeamBillingProduct $product): array
    {
        $overrides = match ($product)
        {
            TeamBillingProduct::TokensMultiplier => config('humano_pricing.token_billing.client_token_multiplier_by_team', []),
            TeamBillingProduct::WhatsappSend => config('humano_pricing.whatsapp_message_billing.our_amount_by_team', []),
            TeamBillingProduct::MailerSend => config('emailer.payg.price_per_email_by_team', []),
        };

        return is_array($overrides) ? $overrides : [];
    }

    private static function inEffect(?int $teamId, TeamBillingProduct $product, Carbon $at): ?self
    {
        return self::query()
            ->where('product', $product->value)
            ->where('team_id', $teamId)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void
            {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    private static function defaultCurrency(TeamBillingProduct $product): ?string
    {
        return match ($product)
        {
            TeamBillingProduct::TokensMultiplier => null,
            TeamBillingProduct::WhatsappSend => strtoupper((string) config('humano_pricing.whatsapp_message_billing.currency', 'EUR')),
            TeamBillingProduct::MailerSend => strtoupper((string) config('emailer.payg.currency', 'EUR')),
        };
    }

    private static function normalizeAmount(TeamBillingProduct $product, float $amount): float
    {
        return match ($product)
        {
            TeamBillingProduct::TokensMultiplier => max(1, $amount),
            TeamBillingProduct::WhatsappSend, TeamBillingProduct::MailerSend => max(0, $amount),
        };
    }

    private static function resolveDate(DateTimeInterface|string|null $on): Carbon
    {
        if ($on instanceof Carbon)
        {
            return $on->copy();
        }

        if ($on instanceof DateTimeInterface)
        {
            return Carbon::instance($on);
        }

        if (is_string($on) && trim($on) !== '')
        {
            return Carbon::parse($on);
        }

        return now();
    }
}
