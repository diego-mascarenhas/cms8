<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProspectImport extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'category_id',
        'filters',
        'status',
        'discovery_complete',
        'total',
        'queued',
        'processed',
        'imported',
        'copied',
        'skipped',
        'failed',
        'credits',
        'finished_at',
        'error',
    ];

    protected $casts = [
        'filters' => 'array',
        'discovery_complete' => 'boolean',
        'finished_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function noteQueued(int $count, int $total, bool $discoveryComplete): void
    {
        DB::transaction(function () use ($count, $total, $discoveryComplete): void
        {
            $import = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if ($import === null || in_array($import->status, ['finished', 'failed'], true))
            {
                return;
            }

            $import->queued = (int) $import->queued + $count;
            $import->total = max((int) $import->total, $total);
            if ($discoveryComplete)
            {
                $import->discovery_complete = true;
            }

            $import->refreshStatus();
            $import->save();
        });
    }

    public function noteContact(string $outcome, int $credits): void
    {
        $column = match ($outcome)
        {
            'copied' => 'copied',
            'skipped' => 'skipped',
            'failed' => 'failed',
            default => 'imported',
        };

        DB::transaction(function () use ($column, $credits): void
        {
            $import = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if ($import === null || in_array($import->status, ['finished', 'failed'], true))
            {
                return;
            }

            $import->{$column} = (int) $import->{$column} + 1;
            $import->processed = (int) $import->processed + 1;
            $import->credits = (int) $import->credits + max(0, $credits);
            $import->refreshStatus();
            $import->save();
        });
    }

    public function markFailed(string $error): void
    {
        DB::transaction(function () use ($error): void
        {
            $import = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if ($import === null || $import->status === 'finished')
            {
                return;
            }

            $import->status = 'failed';
            $import->error = $error;
            $import->finished_at = now();
            $import->save();
        });
    }

    public function usageLabel(): string
    {
        $filters = is_array($this->filters) ? $this->filters : [];
        $locations = $filters['person_locations'] ?? [];
        $place = is_array($locations) && $locations !== []
            ? implode(', ', array_map(strval(...), $locations))
            : trim((string) ($filters['q_keywords'] ?? ''));
        if ($place === '')
        {
            $place = 'Prospección';
        }

        $total = max((int) $this->total, (int) $this->queued);
        $done = $this->status === 'finished' ? $total : (int) $this->processed;
        $progress = $total > 0 ? $done.' de '.$total : (string) $done;
        $state = match ($this->status)
        {
            'finished' => 'terminada',
            'failed' => 'detenida',
            default => 'en curso',
        };

        return 'Importación · '.$place.' · '.$progress.' · '.$state;
    }

    public function refreshStatus(): void
    {
        if (in_array($this->status, ['finished', 'failed'], true))
        {
            return;
        }

        if (! $this->discovery_complete)
        {
            $this->status = 'discovering';

            return;
        }

        if ((int) $this->processed < (int) $this->queued)
        {
            $this->status = 'importing';

            return;
        }

        $this->status = 'finished';
        $this->finished_at = now();
    }
}
