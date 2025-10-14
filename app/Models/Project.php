<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Project extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'enterprise_id',
        'category_id',
        'board_id',
        'name',
        'real_name',
        'description',
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
