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

    public const PROVIDER_UPLOAD = 'upload';

    protected $fillable = [
        'team_id',
        'payment_account_id',
        'provider',
        'period_year',
        'period_month',
        'source',
        'original_filename',
        'storage_path',
        'disk',
        'mime_type',
        'file_size',
        'validation_summary',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'file_size' => 'integer',
        'validation_summary' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function isDownloadable(): bool
    {
        return filled($this->storage_path) && filled($this->disk);
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }

    public function fileIcon(): string
    {
        $name = mb_strtolower((string) ($this->original_filename ?? ''));
        $mime = mb_strtolower((string) ($this->mime_type ?? ''));

        if (str_contains($name, '.csv') || str_contains($mime, 'csv') || str_contains($mime, 'text/'))
        {
            return 'ti ti-file-type-csv';
        }

        if (str_contains($name, '.pdf') || str_contains($mime, 'pdf'))
        {
            return 'ti ti-file-type-pdf';
        }

        return 'ti ti-file-download';
    }
}
