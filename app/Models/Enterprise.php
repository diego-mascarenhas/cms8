<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Enterprise extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'type_id',
        'user_id',
        'assigned_to',
        'referred_by',
        'address',
        'postal_code',
        'locality',
        'province',
        'country',
        'phone',
        'whatsapp',
        'email',
        'website',
        'data',
        'payment_method_id',
        'invoice_type_id',
        'status_id',
    ];

    protected $casts = [
        'data' => 'object',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            $builder->where('team_id', auth()->user()->currentTeam->id);
        });
    }
    public function scopeClients($query)
    {
        return $query->where('type_id', 1);
    }

    public function scopeSuppliers($query)
    {
        return $query->where('type_id', 2);
    }
    
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function type()
    {
        return $this->belongsTo(EnterpriseType::class);
    }
    
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function enterpriseBillingAddresses()
    {
        return $this->hasMany(EnterpriseBillingAddress::class);
    }

    public function enterpriseBillingAddress()
    {
        return $this->enterpriseBillingAddresses()
                    ->where('status', 1)
                    ->latest()
                    ->first();
    }

    public function sentimentHistories()
    {
        return $this->hasMany(EnterpriseSentimentHistory::class);
    }

    public function currentSentiment()
    {
        return $this->hasOne(EnterpriseSentimentHistory::class)->latest();
    }

    public function status()
    {
        return $this->belongsTo(EnterpriseStatus::class);
    }
    
    public function getStatusLabelAttribute()
    {
        if ($this->status) {
            return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->name . '</span>';
        }
        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }
}