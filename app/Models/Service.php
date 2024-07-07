<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'services';

    protected $fillable = [
        'client_id',
        'type_id',
        'description',
        'data',
        'operation',
        'last_billed',
        'next_billing',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'data' => 'object',
    ];

    public function type()
    {
        return $this->belongsTo(ServiceType::class);
    }
    
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
    
    public function getStatusLabelAttribute()
    {
        switch ($this->status) {
            case 1:
                return '<span class="badge rounded-pill bg-label-danger">Suspended</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-warning">To suspend</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-success">To activate</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-info">Active</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">unknown</span>';
        }
    }

    public function getCalculatedPriceAttribute()
    {
        $basePrice = $this->price ?? $this->type->price;
        $discount = $this->discount ?? $this->type->discount;
        $frequency = $this->frequency ?? 1;

        $priceAfterDiscount = $basePrice - ($basePrice * ($discount / 100));

        return $priceAfterDiscount / $frequency;
    }

    public static function calculateTotal($status, $operation)
    {
        $services = self::where('status', $status)
            ->whereHas('type', function ($query) use ($operation) {
                $query->where('operation', $operation);
            })
            ->get();

        $total = 0;

        foreach ($services as $service) {
            $basePrice = $service->price ?? $service->type->price;
            $discount = $service->discount ?? $service->type->discount;
            $frequency = $service->frequency ?? 1;

            $priceAfterDiscount = $basePrice - ($basePrice * ($discount / 100));
            $total += $priceAfterDiscount / $frequency;
        }

        return $total;
    }

    public function billService($monthsToAdd)
    {
        $this->last_billed = Carbon::now();
        $this->next_billing = Carbon::now()->addMonths($monthsToAdd);
        $this->expires_at = $this->next_billing->addMonths($monthsToAdd);
        $this->save();
    }
}
