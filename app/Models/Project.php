<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Project extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'enterprise_id',
        'category_id',
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

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
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

    public function notes()
    {
        return $this->hasMany(Note::class, 'reference')->where('module_id', 1); // Assuming module_id 1 is for projects
    }

    public function collaborators()
    {
        return $this->belongsToMany(Contact::class, 'contact_project')
            ->using(ContactProject::class)
            ->withPivot('message_sent', 'status', 'sent_at', 'viewed_at', 'responded_at', 'response_message', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at'); // Only get non-deleted relationships
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status) {
            return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->translated_name . '</span>';
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
