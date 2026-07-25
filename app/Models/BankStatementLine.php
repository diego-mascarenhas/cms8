<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_statement_id',
        'external_id',
        'reference',
        'occurred_at',
        'amount',
        'currency',
        'payer_name',
        'payer_id_type',
        'payer_id_number',
        'description',
        'payment_sync_id',
        'reconcile_dismissed_at',
        'raw',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'amount' => 'decimal:2',
        'reconcile_dismissed_at' => 'datetime',
        'raw' => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function paymentSync(): BelongsTo
    {
        return $this->belongsTo(PaymentSync::class);
    }

    public function isDismissed(): bool
    {
        return $this->reconcile_dismissed_at !== null;
    }

    public function markDismissed(): void
    {
        $this->forceFill(['reconcile_dismissed_at' => now()])->save();
    }
}
