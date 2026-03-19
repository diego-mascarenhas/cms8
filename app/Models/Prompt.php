<?php

namespace App\Models;

use App\Services\WordPressContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    protected $table = 'module_prompts';

    protected $fillable = [
        'team_id',
        'module_id',
        'section_key',
        'section_label',
        'prompt_instruction',
        'helper_text',
        'is_active',
        'order',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('module_prompts.team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->withoutGlobalScope('team')->where('module_prompts.team_id', $teamId);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    public function scopeForModule(Builder $query, string $moduleKey): Builder
    {
        return $query->whereHas('module', function ($q) use ($moduleKey)
        {
            $q->where('key', $moduleKey);
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Find a prompt by module key and section key (e.g. "contacts:landing").
     * For "landing" only, finds the first prompt with section_key landing.
     * When $teamId is null, uses current user's team (global scope applies).
     */
    public static function findByRoutingKey(string $routingKey, ?int $teamId = null): ?self
    {
        $routingKey = trim($routingKey);
        $query = $teamId !== null ? self::forTeam($teamId) : self::query();

        if (str_contains($routingKey, ':'))
        {
            [$moduleKey, $sectionKey] = explode(':', $routingKey, 2);
            $module = Module::where('key', trim($moduleKey))->first();
            if (! $module)
            {
                return null;
            }

            return $query->where('module_id', $module->id)->where('section_key', trim($sectionKey))->first();
        }

        return $query->where('section_key', $routingKey)->first();
    }

    public function isGeneralRouter(): bool
    {
        return $this->section_key === 'general';
    }

    /**
     * Build the list of routable keys from active prompts (all except general) for the router instruction.
     * Returns a string to inject in place of {{ROUTING_KEYS}} in the general prompt.
     * When $teamId is null, uses current user's team (global scope applies).
     */
    public static function buildRoutableKeysList(?int $teamId = null): string
    {
        $query = $teamId !== null ? self::forTeam($teamId) : self::query();
        $prompts = $query->active()
            ->with('module')
            ->where('section_key', '!=', 'general')
            ->orderBy('order')
            ->get();

        $lines = [];
        foreach ($prompts as $p)
        {
            $key = $p->module
                ? $p->module->key.':'.$p->section_key
                : $p->section_key;
            $lines[] = '- **'.$key.'** → '.$p->section_label;
        }

        return implode("\n", $lines);
    }

    /**
     * Instruction text with placeholders resolved (e.g. {{WORDPRESS_CONTEXT}}).
     * Shared by AssistantChatService and ChatAssistantReplyService when attaching DB prompts to tool flows.
     */
    public function resolvedInstruction(?int $teamId): string
    {
        $instruction = (string) $this->prompt_instruction;

        if (str_contains($instruction, '{{WORDPRESS_CONTEXT}}'))
        {
            if ($teamId && $team = Team::query()->find($teamId))
            {
                $context = WordPressContextService::forTeam($team)->buildContext();
            } else
            {
                $context = '_El contexto de WordPress no está disponible (requiere sesión autenticada con WordPress configurado)._';
            }

            $instruction = str_replace('{{WORDPRESS_CONTEXT}}', $context, $instruction);
        }

        return $instruction;
    }
}
