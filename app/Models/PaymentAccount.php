<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $table = 'payment_accounts';

    protected $fillable = ['team_id', 'code', 'name', 'symbol', 'currency_id', 'status'];

    protected static function booted()
    {
        static::addGlobalScope('team', function ($builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::addGlobalScope('activeStatus', function ($builder)
        {
            $builder->where('status', 1);
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentTypes()
    {
        return $this->belongsToMany(PaymentType::class, 'payment_account_payment_type');
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
        $income = $this->payments()->where('transaction_type', 'income')->sum('amount');
        $expense = $this->payments()->where('transaction_type', 'expense')->sum('amount');

        return $income - $expense;
    }
}
