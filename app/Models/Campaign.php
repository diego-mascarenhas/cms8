<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'campaigns';

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
        'settings',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_rate' => 'decimal:2',
        'clicked_rate' => 'decimal:2',
        'unsubscribed_rate' => 'decimal:2',
        'settings' => 'array',
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

        static::creating(function (Campaign $model)
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

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'campaign_message')
            ->using(CampaignMessagePivot::class)
            ->withPivot(['sort_order', 'delay_minutes_after_previous', 'conditions'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderByPivot('id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class, 'campaign_id');
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

    /**
     * Real-time delivery counters for this campaign (message_deliveries.campaign_id).
     *
     * @return array{
     *     total: int,
     *     unique_recipients: int,
     *     sent: int,
     *     delivered: int,
     *     opened: int,
     *     clicked: int,
     *     bounced: int,
     *     complained: int,
     *     failed: int,
     *     pending: int,
     *     open_rate: float,
     *     click_rate: float
     * }
     */
    public function deliveryStatistics(): array
    {
        $base = MessageDelivery::query()->where('campaign_id', $this->id);

        $total = (clone $base)->count();
        $uniqueRecipients = (int) (clone $base)->selectRaw('count(distinct contact_id) as aggregate')->value('aggregate');

        $sent = (clone $base)->whereNotNull('sent_at')->where('sent_at', '<=', now())->count();
        $delivered = (clone $base)->whereNotNull('delivered_at')->count();
        $opened = (clone $base)->whereNotNull('opened_at')->count();
        $clicked = (clone $base)->whereNotNull('clicked_at')->count();
        $bounced = (clone $base)->whereNotNull('bounced_at')->count();
        $complained = (clone $base)->whereNotNull('complained_at')->count();
        $failed = (clone $base)->where('status_id', 4)->count();
        $pending = (clone $base)->where(function (Builder $q): void
        {
            $q->whereNull('sent_at')
                ->orWhere('sent_at', '>', now());
        })->count();

        $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0.0;
        $clickRate = $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'unique_recipients' => $uniqueRecipients,
            'sent' => $sent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'complained' => $complained,
            'failed' => $failed,
            'pending' => $pending,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
        ];
    }
}
