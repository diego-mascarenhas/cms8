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

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    public function getSubtotalAttribute()
    {
        return $this->unit_price * $this->quantity;
    }

    public function getTaxAmountAttribute()
    {
        return ($this->subtotal - $this->discount) * ($this->tax_percentage / 100);
    }

    public function getTotalAttribute()
    {
        return ($this->subtotal - $this->discount) + $this->tax_amount;
    }
}
