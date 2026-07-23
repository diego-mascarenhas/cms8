<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enterprise extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'code',
        'type_id',
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
        'payment_type_id',
        'invoice_type_id',
        'status_id',
        'creator_id',
        'responsible_id',
    ];

    protected $casts = [
        'data' => 'object',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            // Check if the user is authenticated before accessing currentTeam
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
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

    /**
     * Enterprises linked to a Stripe customer (code stored as cus_…).
     */
    public function scopeWithStripeCustomerCode($query)
    {
        return $query->where('code', 'like', 'cus_%');
    }

    /**
     * Enterprises that currently owe something: local sell invoices with balance,
     * or open/unpaid Stripe invoice_syncs for their customer code.
     */
    public function scopeWithOutstandingBalance($query)
    {
        return $query->where(function ($outer): void
        {
            $outer->whereHas('invoices', function ($invoices): void
            {
                $invoices->where('operation', 'sell')
                    ->where('balance', '>', 0);
            })->orWhereExists(function ($sub): void
            {
                $sub->selectRaw('1')
                    ->from('invoice_syncs')
                    ->whereColumn('invoice_syncs.customer_id', 'enterprises.code')
                    ->whereColumn('invoice_syncs.team_id', 'enterprises.team_id')
                    ->where('invoice_syncs.provider', 'stripe')
                    ->where(function ($status): void
                    {
                        $status->whereIn('invoice_syncs.status', ['open', 'uncollectible'])
                            ->orWhere(function ($unpaid): void
                            {
                                $unpaid->where('invoice_syncs.paid', false)
                                    ->where('invoice_syncs.amount_remaining', '>', 0);
                            });
                    });
            });
        });
    }

    /**
     * Stripe clients that can receive an MP assignment: open balance, or paid Stripe
     * invoices that still lack mercadopago_id metadata (backfill).
     */
    public function scopeWithMercadoPagoAssignableInvoices($query)
    {
        return $query->where(function ($outer): void
        {
            $outer->whereHas('invoices', function ($invoices): void
            {
                $invoices->where('operation', 'sell')
                    ->where('balance', '>', 0);
            })->orWhereExists(function ($sub): void
            {
                $sub->selectRaw('1')
                    ->from('invoice_syncs')
                    ->whereColumn('invoice_syncs.customer_id', 'enterprises.code')
                    ->whereColumn('invoice_syncs.team_id', 'enterprises.team_id')
                    ->where('invoice_syncs.provider', 'stripe')
                    ->where(function ($status): void
                    {
                        $status->whereIn('invoice_syncs.status', ['open', 'uncollectible'])
                            ->orWhere(function ($unpaid): void
                            {
                                $unpaid->where('invoice_syncs.paid', false)
                                    ->where('invoice_syncs.amount_remaining', '>', 0);
                            })
                            ->orWhere(function ($paid): void
                            {
                                $paid->where(function ($paidStatus): void
                                {
                                    $paidStatus->where('invoice_syncs.paid', true)
                                        ->orWhere('invoice_syncs.status', 'paid');
                                })->whereRaw("(
                                    NULLIF(TRIM(COALESCE(
                                        invoice_syncs.raw_payload->'metadata'->>'mercadopago_id',
                                        invoice_syncs.raw_payload->'metadata'->>'mercadopago_payment_id',
                                        ''
                                    )), '') IS NULL
                                )");
                            });
                    });
            });
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
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

    public function status()
    {
        return $this->belongsTo(EnterpriseStatus::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'enterprise_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'enterprise_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'enterprise_id');
    }

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_enterprise')
            ->withPivot(['position', 'department_id', 'superior_id'])
            ->withTimestamps();
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status)
        {
            return '<span class="badge rounded-pill '.$this->status->label_class.'">'.$this->status->name.'</span>';
        }

        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

    public static function getContactStats($teamId)
    {
        $statusLabels = [
            1 => 'Leads',
            2 => 'FollowUp',
            6 => 'Clients',
            7 => 'Finished',
        ];

        // Optimize: Use database aggregation instead of loading all records
        $contactStats = self::where('team_id', $teamId)
            ->whereIn('status_id', array_keys($statusLabels))
            ->selectRaw('status_id, COUNT(*) as count')
            ->groupBy('status_id')
            ->pluck('count', 'status_id');

        $totalContacts = $contactStats->sum();

        $data = ['totalContacts' => $totalContacts];
        foreach ($statusLabels as $statusId => $label)
        {
            $count = $contactStats[$statusId] ?? 0;
            $percentage = $totalContacts > 0 ? round(($count / $totalContacts) * 100, 2) : 0;
            $data["total$label"] = $count;
            $data[lcfirst($label).'Percentage'] = $percentage;
        }

        $defaultData = [
            'totalContacts' => 0,
            'totalLeads' => 0,
            'leadsPercentage' => 0,
            'totalClients' => 0,
            'clientsPercentage' => 0,
            'totalFollowUp' => 0,
            'followUpPercentage' => 0,
            'totalFinished' => 0,
            'finishedPercentage' => 0,
        ];

        $finalData = array_merge($defaultData, $data);

        return $finalData;
    }

    /**
     * Get the Stripe customer ID
     */
    public function getStripeCustomerId()
    {
        return $this->code_type === 'stripe_customer' ? $this->code : null;
    }

    /**
     * Set the Stripe customer ID
     */
    public function setStripeCustomerId($customerId)
    {
        $this->code = $customerId;
        $this->code_type = 'stripe_customer';

        return $this;
    }

    public function scopeActiveClients($query)
    {
        return $query->where('status_id', 2);
    }
}
