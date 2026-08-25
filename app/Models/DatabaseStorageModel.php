<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseStorageModel extends Model
{
    public const CART_DATA_PREFIX = 'b64:';

    protected $table = 'cart_storage';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'cart_data',
    ];

    public function setCartDataAttribute($value)
    {
        $this->attributes['cart_data'] = self::CART_DATA_PREFIX.base64_encode(serialize($value));
    }

    public function getCartDataAttribute($value)
    {
        if ($value === null || $value === '')
        {
            return [];
        }

        if (! is_string($value))
        {
            return $value;
        }

        $payload = $this->payloadForUnserialize($value);
        if ($payload === null)
        {
            return [];
        }

        try
        {
            $decoded = unserialize($payload);
            if ($decoded === false && $payload !== 'b:0;')
            {
                $this->logUnreadableCartData('unserialize returned false');

                return [];
            }

            return $decoded;
        } catch (Throwable $e)
        {
            $this->logUnreadableCartData($e->getMessage());

            return [];
        }
    }

    private function payloadForUnserialize(string $value): ?string
    {
        if (str_starts_with($value, self::CART_DATA_PREFIX))
        {
            $decoded = base64_decode(substr($value, strlen(self::CART_DATA_PREFIX)), true);
            if ($decoded === false)
            {
                $this->logUnreadableCartData('invalid base64 payload');

                return null;
            }

            return $decoded;
        }

        return $value;
    }

    private function logUnreadableCartData(string $reason): void
    {
        Log::warning('Skipped unreadable cart_storage row', [
            'id' => $this->attributes['id'] ?? null,
            'reason' => $reason,
        ]);
    }
}
