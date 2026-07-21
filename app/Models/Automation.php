<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Automation extends Model
{
    /** @use HasFactory<\Database\Factories\AutomationFactory> */
    use HasFactory;

    public const CHANNEL_HUMANO = 'humano';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_CHAT = 'chat';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_API = 'api';

    /** @var list<string> */
    public const CHANNELS = [
        self::CHANNEL_HUMANO,
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_CHAT,
        self::CHANNEL_EMAIL,
        self::CHANNEL_API,
    ];

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'is_active',
        'entry_prompt_key',
        'channels',
        'public_token',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'channels' => 'array',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('automations.team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (Automation $automation)
        {
            if ($automation->slug === null || $automation->slug === '')
            {
                $automation->slug = Str::slug($automation->name);
            }

            if ($automation->public_token === null || $automation->public_token === '')
            {
                $automation->public_token = bin2hex(random_bytes(32));
            }

            if ($automation->channels === null)
            {
                $automation->channels = self::defaultChannels();
            }
        });
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultChannels(): array
    {
        return [
            self::CHANNEL_HUMANO => true,
            self::CHANNEL_WHATSAPP => false,
            self::CHANNEL_CHAT => true,
            self::CHANNEL_EMAIL => false,
            self::CHANNEL_API => true,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->withoutGlobalScope('team')->where('automations.team_id', $teamId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function allowsChannel(string $channel): bool
    {
        $channels = is_array($this->channels) ? $this->channels : [];

        return (bool) ($channels[$channel] ?? false);
    }

    /**
     * Entry prompt routing key, or null to use the general router.
     */
    public function resolvedEntryPromptKey(): ?string
    {
        $key = is_string($this->entry_prompt_key) ? trim($this->entry_prompt_key) : '';

        return $key !== '' ? $key : null;
    }

    public function regeneratePublicToken(): string
    {
        $this->public_token = bin2hex(random_bytes(32));
        $this->save();

        return $this->public_token;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public static function normalizeChannels(array $input, bool $missingAsFalse = true): array
    {
        $defaults = self::defaultChannels();
        $normalized = [];
        foreach (self::CHANNELS as $channel)
        {
            if (array_key_exists($channel, $input))
            {
                $normalized[$channel] = filter_var($input[$channel], FILTER_VALIDATE_BOOLEAN);
            } else
            {
                $normalized[$channel] = $missingAsFalse ? false : ($defaults[$channel] ?? false);
            }
        }

        return $normalized;
    }
}
