<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}