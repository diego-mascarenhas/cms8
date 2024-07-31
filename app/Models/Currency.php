<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',  // Asegura que el estado se maneje como un booleano
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function paymentAccounts()
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function getFormattedAttribute()
    {
        return $this->symbol . ' - ' . $this->name;
    }
}
