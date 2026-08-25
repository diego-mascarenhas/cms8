<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseStorageModel extends Model
{
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
        $this->attributes['cart_data'] = serialize($value);
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

        try
        {
            $decoded = unserialize($value);
            if ($decoded === false && $value !== 'b:0;')
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

    private function logUnreadableCartData(string $reason): void
    {
        Log::warning('Skipped unreadable cart_storage row', [
            'id' => $this->attributes['id'] ?? null,
            'reason' => $reason,
        ]);
    }
}
