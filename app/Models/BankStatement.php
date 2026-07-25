<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    public const SOURCE_API = 'api';

    public const SOURCE_UPLOAD = 'upload';

    public const PROVIDER_MERCADOPAGO = 'mercadopago';

    protected $fillable = [
        'team_id',
        'provider',
        'period_year',
        'period_month',
        'source',
        'original_filename',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
