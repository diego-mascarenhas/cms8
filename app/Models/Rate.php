<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'description',
        'collaborator_id',
        'service_type',
        'base_price',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the collaborator that owns the rate
     */
    public function collaborator()
    {
        return $this->belongsTo(Contact::class, 'collaborator_id');
    }

    /**
     * Get the formatted price with currency
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->base_price, 2) . ' ' . $this->currency;
    }

    /**
     * Scope for active rates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rates by service type
     */
    public function scopeByServiceType($query, $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }
}
