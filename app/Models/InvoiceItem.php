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

    /**
     * Get the category associated with the invoice item.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
