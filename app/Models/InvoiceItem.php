<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'category_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_percentage',
    ];

    protected static function booted()
    {
        static::addGlobalScope('itemsFromJuly2024', function ($builder) {
            $builder->where('created_at', '>=', '2024-07-01 00:00:00');
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
