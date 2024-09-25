<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Contact extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'name',
        'channel_id',
        'birthday',
        'profile',
        'engagment',
        'country',
        'language',
        'creator_id',
        'responsible_id',
        'data',
        'status_id',
    ];

    protected $casts = [
        'data' => 'object',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }
    
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    
    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function sentimentHistories()
    {
        return $this->hasMany(ContactSentimentHistory::class);
    }

    public function currentSentiment()
    {
        return $this->hasOne(ContactSentimentHistory::class)->latest();
    }

    public function status()
    {
        return $this->belongsTo(ContactStatus::class);
    }
    
    public function getStatusLabelAttribute()
    {
        if ($this->status) {
            return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->name . '</span>';
        }
        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

    public static function getContactStats($teamId)
    {
        $statusLabels = [
            1 => 'Leads',
            2 => 'FollowUp',
            6 => 'Clients',
            7 => 'Finished',
        ];

        $contactStats = self::where('team_id', $teamId)
            ->whereIn('status_id', array_keys($statusLabels))
            ->get()
            ->groupBy('status_id')
            ->map(function ($group) {
                return $group->count();
            });

        $totalContacts = $contactStats->sum();

        $data = ['totalContacts' => $totalContacts];
        foreach ($statusLabels as $statusId => $label) {
            $count = $contactStats[$statusId] ?? 0;
            $percentage = $totalContacts > 0 ? round(($count / $totalContacts) * 100, 2) : 0;
            $data["total$label"] = $count;
            $data[lcfirst($label) . "Percentage"] = $percentage;
        }

        $defaultData = [
            'totalContacts' => 0,
            'totalLeads' => 0,
            'leadsPercentage' => 0,
            'totalClients' => 0,
            'clientsPercentage' => 0,
            'totalFollowUp' => 0,
            'followUpPercentage' => 0,
            'totalFinished' => 0,
            'finishedPercentage' => 0,
        ];

        $finalData = array_merge($defaultData, $data);

        return $finalData;
    }
}