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
}
