<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFare extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fare_id',
        'language_origin_id',
        'language_destination_id',
        'currency_id',
        'amount',
        'negotiable',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'negotiable' => 'boolean',
    ];

    /**
     * Get the user that owns this fare
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the fare that owns this user fare
     */
    public function fare()
    {
        return $this->belongsTo(Fare::class);
    }

    /**
     * Get the origin language
     */
    public function languageOrigin()
    {
        return $this->belongsTo(LanguageVariant::class, 'language_origin_id', 'code');
    }

    /**
     * Get the destination language
     */
    public function languageDestination()
    {
        return $this->belongsTo(LanguageVariant::class, 'language_destination_id', 'code');
    }

    /**
     * Get the currency
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'code');
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute()
    {
        $currencySymbol = $this->currency ? $this->currency->symbol : '€';

        return $currencySymbol.' '.number_format($this->amount, 2);
    }
}
