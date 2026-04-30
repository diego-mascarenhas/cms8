<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaign extends Model
{
    use HasFactory;

    protected $table = 'email_campaigns';

    protected $fillable = [
        'team_id',
        'name',
        'type',
        'status',
        'summary',
        'sends_count',
        'opened_rate',
        'clicked_rate',
        'unsubscribed_rate',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_rate' => 'decimal:2',
        'clicked_rate' => 'decimal:2',
        'unsubscribed_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (EmailCampaign $model)
        {
            if (! $model->team_id && auth()->check())
            {
                $model->team_id = auth()->user()->currentTeam->id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function typeLabel(): string
    {
        $case = CampaignType::tryFrom($this->type);

        return $case ? $case->label() : $this->type;
    }

    public function statusLabel(): string
    {
        $case = CampaignStatus::tryFrom($this->status);

        return $case ? $case->label() : $this->status;
    }

    public function statusBadgeClasses(): string
    {
        $case = CampaignStatus::tryFrom($this->status);

        return $case ? $case->badgeClasses() : 'bg-label-secondary text-secondary';
    }
}
