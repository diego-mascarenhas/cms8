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
use Illuminate\Support\Facades\DB;

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

    /**
     * Estado alineado con la barra Enviar/Pausar: "Activo" solo si hay envío en curso
     * (mensaje activo y con entregas o fecha de inicio); "Listo para enviar" si está activado
     * pero aún sin ese contexto (mismo caso que el botón Enviar ahora).
     */
    public function effectiveStatus(): CampaignStatus
    {
        $stored = CampaignStatus::tryFrom($this->status);

        if ($stored === CampaignStatus::Sent || $stored === CampaignStatus::Scheduled)
        {
            return $stored;
        }

        if (! $this->relationLoaded('messages'))
        {
            $this->load([
                'messages' => function ($q): void
                {
                    $q->select('messages.id', 'messages.team_id', 'messages.status_id', 'messages.started_at');
                },
            ]);
        }

        if ($this->messages->isEmpty())
        {
            return $stored ?? CampaignStatus::Active;
        }

        $messageIds = $this->messages->pluck('id');

        foreach ($this->messages as $message)
        {
            if (self::messageIsOperationalForToolbar($message))
            {
                return CampaignStatus::Active;
            }
        }

        $anyStatusTrue = $this->messages->contains(fn ($m): bool => (bool) $m->status_id);

        if ($anyStatusTrue)
        {
            return CampaignStatus::PendingLaunch;
        }

        $anyStarted = $this->messages->contains(fn ($m): bool => $m->started_at !== null);

        $hasDeliveries = MessageDelivery::query()
            ->whereIn('message_id', $messageIds)
            ->exists();

        if ($anyStarted || $hasDeliveries)
        {
            return CampaignStatus::Paused;
        }

        return CampaignStatus::Active;
    }

    /**
     * Misma regla que Pausar / Activo en listado y ficha de campaña (envío en curso).
     */
    public static function messageIsOperationalForToolbar(Message $message): bool
    {
        $totalDeliveries = MessageDelivery::where('message_id', $message->id)->count();

        return (bool) $message->status_id
            && ($totalDeliveries > 0 || $message->started_at !== null);
    }

    public function statusLabel(): string
    {
        return $this->effectiveStatus()->label();
    }

    public function statusBadgeClasses(): string
    {
        return $this->effectiveStatus()->badgeClasses();
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
        $now = now();

        $row = DB::table('message_deliveries')
            ->where('campaign_id', $this->id)
            ->selectRaw(
                'COUNT(*) as total_deliveries,
                COUNT(DISTINCT contact_id) as unique_recipients_raw,
                SUM(CASE WHEN sent_at IS NOT NULL AND sent_at <= ? THEN 1 ELSE 0 END) as sent_raw,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered_raw,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_raw,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked_raw,
                SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced_raw,
                SUM(CASE WHEN complained_at IS NOT NULL THEN 1 ELSE 0 END) as complained_raw,
                SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as failed_raw,
                SUM(CASE WHEN sent_at IS NULL OR sent_at > ? THEN 1 ELSE 0 END) as pending_raw',
                [$now, $now],
            )
            ->first();

        $total = (int) ($row->total_deliveries ?? 0);
        $uniqueRecipients = (int) ($row->unique_recipients_raw ?? 0);
        $sent = (int) ($row->sent_raw ?? 0);
        $delivered = (int) ($row->delivered_raw ?? 0);
        $opened = (int) ($row->opened_raw ?? 0);
        $clicked = (int) ($row->clicked_raw ?? 0);
        $bounced = (int) ($row->bounced_raw ?? 0);
        $complained = (int) ($row->complained_raw ?? 0);
        $failed = (int) ($row->failed_raw ?? 0);
        $pending = (int) ($row->pending_raw ?? 0);

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
