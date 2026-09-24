<?php

namespace App\Models;

use App\Enums\TeamBillingFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamUsageInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\TeamUsageInvoiceFactory> */
    use HasFactory;

    public const KIND_CYCLE = 'cycle';

    public const KIND_ADJUSTMENT = 'adjustment';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'team_id',
        'kind',
        'frequency',
        'period_from',
        'period_to',
        'billed_cents',
        'currency',
        'stripe_invoice_id',
        'status',
        'adjustment_id',
        'issued_at',
    ];

    protected $casts = [
        'frequency' => TeamBillingFrequency::class,
        'period_from' => 'datetime',
        'period_to' => 'datetime',
        'issued_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(TeamUsageInvoiceAdjustment::class, 'adjustment_id');
    }
}
