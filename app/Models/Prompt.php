<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    protected $table = 'module_prompts';

    protected $fillable = [
        'module_id',
        'section_key',
        'section_label',
        'prompt_instruction',
        'helper_text',
        'is_active',
        'order',
    ];

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
     */
    public static function findByRoutingKey(string $routingKey): ?self
    {
        $routingKey = trim($routingKey);
        if (str_contains($routingKey, ':'))
        {
            [$moduleKey, $sectionKey] = explode(':', $routingKey, 2);
            $module = Module::where('key', trim($moduleKey))->first();
            if (! $module)
            {
                return null;
            }

            return self::where('module_id', $module->id)->where('section_key', trim($sectionKey))->first();
        }

        return self::where('section_key', $routingKey)->first();
    }

    public function isGeneralRouter(): bool
    {
        return $this->section_key === 'general';
    }
}
