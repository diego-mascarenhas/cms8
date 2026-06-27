<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'payment_types';

    protected $fillable = ['name', 'status'];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAccounts()
    {
        return $this->belongsToMany(PaymentAccount::class, 'payment_account_payment_type');
    }

    public function getDisplayNameAttribute(): string
    {
        return self::displayNameFor((string) $this->name);
    }

    public static function displayNameFor(string $name): string
    {
        $key = 'payment_types.'.$name;
        $translated = trans($key);

        return is_string($translated) && $translated !== $key ? $translated : $name;
    }

    public static function getOptions()
    {
        return self::all()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => self::displayNameFor((string) $data->name),
            ];
        });
    }
}
