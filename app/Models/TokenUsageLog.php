<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'module_id',
        'service',
        'json_size',
        'toon_size',
        'json_tokens',
        'toon_tokens',
        'savings_percentage',
        'used_toon',
    ];

    protected $casts = [
        'used_toon' => 'boolean',
        'json_size' => 'integer',
        'toon_size' => 'integer',
        'json_tokens' => 'integer',
        'toon_tokens' => 'integer',
        'savings_percentage' => 'integer',
    ];

    /**
     * Boot method to add global scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    /**
     * Get the team that owns the log
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the module that this log belongs to
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get module ID from context (route, request, or URL)
     */
    public static function inferModuleId(): ?int
    {
        if (! request())
        {
            return null;
        }

        // Try to get module from current route name
        $routeName = request()->route()?->getName();
        if ($routeName)
        {
            // Extract module key from route name (e.g., 'contact.show' -> 'contacts')
            $parts = explode('.', $routeName);
            if (! empty($parts[0]))
            {
                $moduleKey = $parts[0];
                // Handle singular to plural conversion for common patterns
                $pluralMap = [
                    'contact' => 'contacts',
                    'project' => 'projects',
                    'task' => 'tasks',
                    'enterprise' => 'enterprises',
                    'invoice' => 'invoices',
                    'payment' => 'payments',
                    'prompt' => 'prompts',
                    'performance-insight' => 'performance_insights',
                    'performance_insights' => 'performance_insights',
                ];
                $moduleKey = $pluralMap[$moduleKey] ?? $moduleKey;

                $module = Module::where('key', $moduleKey)->first();
                if ($module)
                {
                    return $module->id;
                }
            }
        }

        // Try to get module from URL path
        $path = request()->path();
        $firstSegment = explode('/', $path)[0] ?? null;
        if ($firstSegment)
        {
            $module = Module::where('key', $firstSegment)->first();
            if ($module)
            {
                return $module->id;
            }
        }

        return null;
    }

    /**
     * Get total API calls
     */
    public static function getTotalCalls(): int
    {
        return self::count();
    }

    /**
     * Get total tokens saved using Toon
     */
    public static function getTotalTokensSaved(): int
    {
        return (int) (self::where('used_toon', true)
            ->selectRaw('COALESCE(SUM(json_tokens - toon_tokens), 0) as total_saved')
            ->value('total_saved') ?? 0);
    }

    /**
     * Get average savings percentage
     */
    public static function getAverageSavingsPercentage(): float
    {
        return round(
            self::where('used_toon', true)->avg('savings_percentage') ?? 0,
            2,
        );
    }

    /**
     * Get total tokens used (with Toon optimization)
     */
    public static function getTotalTokensUsed(): int
    {
        return self::where('used_toon', true)->sum('toon_tokens') +
               self::where('used_toon', false)->sum('json_tokens');
    }

    /**
     * Get total tokens that would have been used without Toon
     */
    public static function getTotalTokensWithoutToon(): int
    {
        return self::sum('json_tokens');
    }

    /**
     * Get calls count by service
     */
    public static function getCallsByService(): array
    {
        return self::query()
            ->select('service')
            ->selectRaw('count(*) as aggregate_count')
            ->groupBy('service')
            ->pluck('aggregate_count', 'service')
            ->toArray();
    }

    /**
     * Get calls count by module
     */
    public static function getCallsByModule(): array
    {
        return self::whereNotNull('module_id')
            ->with('module:id,name')
            ->get()
            ->groupBy('module_id')
            ->map(function ($logs)
            {
                return [
                    'module_name' => $logs->first()->module->name ?? 'Unknown',
                    'count' => $logs->count(),
                    'tokens_used' => $logs->sum(function ($log)
                    {
                        return $log->used_toon ? $log->toon_tokens : $log->json_tokens;
                    }),
                    'tokens_saved' => $logs->where('used_toon', true)->sum(function ($log)
                    {
                        return $log->json_tokens - $log->toon_tokens;
                    }),
                ];
            })
            ->toArray();
    }
}
