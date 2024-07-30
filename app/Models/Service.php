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
        'enterprise_id',
        'category_id',
        'operation',
        'description',
        'data',
        'currency_id',
        'price',
        'discount',
        'frequency',
        'next_billing',
        'last_billed',
        'expires_at',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'object',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function client()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
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
        $dataField = $this->category->data;

        $categoryData = [];

        if (is_string($dataField))
        {
            $decodedData = json_decode($dataField, true);

            if (json_last_error() === JSON_ERROR_NONE)
            {
                $categoryData = $decodedData;
            }
        }
        elseif (is_array($dataField))
        {
            $categoryData = $dataField;
        }
        elseif (is_object($dataField))
        {
            $categoryData = (array) $dataField;
        }

        if ($this->price !== null && $this->price != 0)
        {
            $basePrice = $this->price;
            $discount = $this->discount ?? 0;
            $frequency = $this->frequency ?? 1;
        }
        else
        {
            $basePrice = $categoryData['price'] ?? 0;
            $discount = $categoryData['discount'] ?? 0;
            $frequency = $categoryData['frequency'] ?? 1;
        }

        $priceAfterDiscount = $basePrice - ($basePrice * ($discount / 100));

        return $priceAfterDiscount / $frequency;
    }

    public static function calculateTotal($status, $operation)
    {
        $services = self::where('status', $status)
            ->whereHas('category', function ($query) use ($operation)
            {
                $query->where('operation', $operation);
            })
            ->get();

        $total = 0;

        foreach ($services as $service)
        {
            $total += $service->calculated_price;
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
