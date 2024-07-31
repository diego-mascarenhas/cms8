<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'payment_accounts';

    protected $fillable = ['code', 'name', 'symbol', 'status'];

    protected static function booted()
    {
        static::addGlobalScope('activeStatus', function ($builder) {
            $builder->where('status', 1);
        });
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
    
    public function payments()
    {
        return $this->hasMany(Payment::class, 'account_id');
    }

    public static function getOptions()
    {
        return self::all()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }

    public function getTotalAmountAttribute()
    {
        $income = $this->payments()->where('transaction_type', 'I')->sum('amount');
        $expense = $this->payments()->where('transaction_type', 'E')->sum('amount');
        return $income - $expense;
    }
}
