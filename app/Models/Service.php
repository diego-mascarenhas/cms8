<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'services';

    protected static function booted()
    {
        // Team scope via related enterprise
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->whereHas('client', function ($q)
                {
                    $q->where('team_id', auth()->user()->currentTeam->id);
                });
            }
        });

        // Ownership for non-admins
        static::addGlobalScope('ownership', function (Builder $builder)
        {
            if (auth()->check() && ! auth()->user()->hasRole('admin'))
            {
                $builder->where('responsible_id', auth()->id());
            }
        });
    }

    protected $fillable = [
        'enterprise_id',
        'subscription_id',
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
        'responsible_id',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'next_billing' => 'date',
        'last_billed' => 'date',
        'expires_at' => 'date',
    ];

    /**
     * Set the data attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setDataAttribute($value)
    {
        if (is_array($value))
        {
            $this->attributes['data'] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else
        {
            $this->attributes['data'] = $value;
        }
    }

    /**
     * Get the data attribute.
     *
     * @param  string  $value
     * @return array
     */
    public function getDataAttribute($value)
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @deprecated Use category() instead.
     */
    public function serviceType()
    {
        return $this->category();
    }

    public function client()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    /**
     * Provider service sync row (service_syncs, from /subscription) this service is linked to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function serviceSync()
    {
        return $this->belongsTo(ServiceSync::class, 'subscription_id');
    }

    /**
     * Backward-compatible alias for legacy callers.
     */
    public function stripeSubscription()
    {
        return $this->serviceSync();
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get domain from data
     */
    public function getDomainAttribute()
    {
        return $this->data['domain'] ?? null;
    }

    /**
     * Get server URL from data
     */
    public function getServerUrlAttribute()
    {
        return $this->data['server_url'] ?? null;
    }

    /**
     * Get username from data
     */
    public function getUsernameAttribute()
    {
        return $this->data['username'] ?? null;
    }

    /**
     * Get service name from data
     */
    public function getServiceNameAttribute()
    {
        return $this->data['serviceName'] ?? null;
    }

    /**
     * Get unique key from data
     */
    public function getUniqueKeyAttribute()
    {
        return $this->data['unique_key'] ?? null;
    }

    /**
     * Get state from data
     */
    public function getStateAttribute()
    {
        return $this->data['state'] ?? null;
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
            case 1:
                return '<span class="badge rounded-pill bg-label-secondary">Suspendido</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-success">Suspender</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-warning">Activar</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-success">Activo</span>';
            case 5:
                return '<span class="badge rounded-pill bg-label-danger">Migrar</span>';
            case 6:
                return '<span class="badge rounded-pill bg-label-warning">Migrando</span>';
            case 7:
                return '<span class="badge rounded-pill bg-label-warning">Delegar</span>';
            case 8:
                return '<span class="badge rounded-pill bg-label-info">Analizar</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">unknown</span>';
        }
    }

    public function getCalculatedPriceAttribute()
    {
        $categoryData = [];

        if ($this->category !== null && is_array($this->category->data))
        {
            $categoryData = $this->category->data;
        }

        if ($this->price !== null && $this->price != 0)
        {
            $basePrice = $this->price;
            $discount = $this->discount ?? 0;
            $frequency = $this->frequency ?? 1;
        } elseif ($this->category !== null)
        {
            $basePrice = $categoryData['price'] ?? 0;
            $discount = $categoryData['discount'] ?? 0;
            $frequency = $categoryData['frequency'] ?? 1;
        } else
        {
            return 0.0;
        }

        if ($frequency === null || $frequency <= 0)
        {
            $frequency = 1;
        }

        $priceAfterDiscount = $basePrice - ($basePrice * ($discount / 100));

        return $priceAfterDiscount / $frequency;
    }

    public static function calculateTotal($status, $operation)
    {
        $services = self::where(function ($query) use ($status)
        {
            // If status is specifically provided, use exact status
            if (is_numeric($status))
            {
                // For status 4, include all status >= 4 (all active statuses)
                if ($status == 4)
                {
                    $query->where('status', '>=', 4);
                } else
                {
                    $query->where('status', $status);
                }
            }
        })
            ->where('operation', $operation)
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
