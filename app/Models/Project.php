<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'enterprise_id',
        'category_id',
        'board_id',
        'name',
        'real_name',
        'description',
        'data',
        'price',
        'discount',
        'cost',
        'date_material',
        'date_start',
        'date_end',
        'responsible_id',
        'status_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date_material' => 'date',
        'date_start' => 'date',
        'date_end' => 'date',
        'deleted_at' => 'datetime',
        'data' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        // Ownership: non-admin users only see their assigned projects
        static::addGlobalScope('ownership', function (Builder $builder)
        {
            if (auth()->check())
            {
                $user = auth()->user();
                if (! $user->hasRole('admin'))
                {
                    $builder->where('responsible_id', $user->id);
                }
            }
        });
    }

    /**
     * Project key (hash of id) for API/external tools. Use in .env as HUMANO_PROJECT_KEY.
     */
    public static function keyFromId(int $id): string
    {
        return hash('sha256', 'humano_project_'.$id);
    }

    public function getProjectKeyAttribute(): string
    {
        return static::keyFromId((int) $this->id);
    }

    /**
     * Resolve project by its key (hash of id). Used for unauthenticated time reporting.
     */
    public static function findByKey(string $key): ?self
    {
        $key = trim($key);
        if ($key === '' || strlen($key) !== 64)
        {
            return null;
        }

        $ids = static::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($ids as $id)
        {
            if (static::keyFromId((int) $id) === $key)
            {
                return static::withoutGlobalScopes()->find($id);
            }
        }

        return null;
    }

    /**
     * Context key (project + user) for MCP/API: allows assigning tasks to a specific user when they select one.
     * Use in .env as HUMANO_CONTEXT_KEY so the MCP can auto-assign and set "in progress" on task selection.
     */
    public function contextKeyForUser(\App\Models\User $user): string
    {
        $payload = [
            'k' => $this->project_key,
            'u' => $user->id,
        ];

        return \Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload));
    }

    /**
     * Decode context key to project_key and user_id. Returns null if invalid.
     *
     * @return array{project_key: string, user_id: int}|null
     */
    public static function decodeContextKey(string $contextKey): ?array
    {
        $contextKey = trim($contextKey);
        if ($contextKey === '')
        {
            return null;
        }

        try
        {
            $json = \Illuminate\Support\Facades\Crypt::decryptString($contextKey);
            $data = json_decode($json, true);
            if (! is_array($data) || empty($data['k']) || empty($data['u']))
            {
                return null;
            }

            return [
                'project_key' => (string) $data['k'],
                'user_id' => (int) $data['u'],
            ];
        } catch (\Throwable $e)
        {
            return null;
        }
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function client()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function status()
    {
        return $this->belongsTo(ProjectStatus::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function board()
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'reference')->where('module_id', 1); // Assuming module_id 1 is for projects
    }

    /**
     * Get total hours worked on this project (from tasks and time entries)
     */
    public function getTotalHoursAttribute()
    {
        if (! $this->board_id)
        {
            return 0;
        }

        // Get all tasks from this project's board
        $tasks = Task::where('board_id', $this->board_id)->pluck('id');

        if ($tasks->isEmpty())
        {
            return 0;
        }

        // Sum all time entries for these tasks
        $totalSeconds = Time::whereIn('task_id', $tasks)->sum('duration_seconds');

        return round($totalSeconds / 3600, 1); // Convert to hours with 1 decimal
    }

    /**
     * Get estimated hours from all tasks in the project
     */
    public function getEstimatedHoursAttribute()
    {
        if (! $this->board_id)
        {
            return 0;
        }

        $totalHours = Task::where('board_id', $this->board_id)
            ->sum('estimated_hours');

        return round($totalHours, 1);
    }

    public function collaborators()
    {
        return $this->belongsToMany(Contact::class, 'contact_project')
            ->using(ContactProject::class)
            ->withPivot('message_sent', 'status', 'sent_at', 'viewed_at', 'responded_at', 'response_message', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at') // Only get non-deleted relationships
            ->orderByRaw('valoration_id IS NULL, valoration_id ASC'); // Order by valoration (lower ID = higher priority, NULL values last)
    }

    public function allCollaborators()
    {
        return $this->belongsToMany(Contact::class, 'contact_project')
            ->using(ContactProject::class)
            ->withPivot('message_sent', 'status', 'sent_at', 'viewed_at', 'responded_at', 'response_message', 'deleted_at')
            ->withTimestamps()
            ->orderByRaw('valoration_id IS NULL, valoration_id ASC'); // Order by valoration (lower ID = higher priority, NULL values last)
    }

    public function projectFares()
    {
        return $this->hasMany(ProjectFare::class);
    }

    public function fares()
    {
        return $this->belongsToMany(Fare::class, 'project_fares')
            ->withPivot('source_language_code', 'target_language_code', 'quantity', 'unit', 'id')
            ->withTimestamps();
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status)
        {
            return '<span class="badge rounded-pill '.$this->status->label_class.'">'.$this->status->translated_name.'</span>';
        }

        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

    /**
     * Get count of active projects (projects in progress states)
     */
    public static function getActiveProjectsCount($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        // Active project statuses: AUTHORIZED, APPROVED, WAITING_FOR_RESPONSE, IN_PROGRESS
        $activeStatuses = [3, 7, 8, 9];

        return static::where('team_id', $teamId)
            ->whereIn('status_id', $activeStatuses)
            ->count();
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'real_name', 'description', 'price', 'discount', 'cost', 'date_start', 'date_end', 'responsible_id', 'status_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
