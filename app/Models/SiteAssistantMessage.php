<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAssistantMessage extends Model
{
    use HasFactory;

    public const ROLE_VISITOR = 'visitor';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_STAFF = 'staff';

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    protected $fillable = [
        'team_id',
        'automation_id',
        'session_id',
        'session_key',
        'contact_id',
        'user_id',
        'role',
        'channel',
        'body',
        'media',
    ];

    protected $casts = [
        'media' => 'array',
    ];

    public static function normalizeOrigin(?string $channel): string
    {
        $channel = strtolower(trim((string) $channel));

        return $channel === self::CHANNEL_MOBILE ? self::CHANNEL_MOBILE : self::CHANNEL_WEB;
    }

    public function originChannel(): string
    {
        return self::normalizeOrigin($this->channel);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('site_assistant_messages.team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AutomationFlowSession::class, 'session_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
