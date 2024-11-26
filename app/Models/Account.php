<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'teams';

    protected $fillable = [
        'name',
        'user_id',
        'personal_team'
    ];

    protected $appends = ['active_clients_count'];

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'team_id');
    }

    public function getActiveClientsCountAttribute()
    {
        return $this->contacts()
            ->withoutGlobalScope('team')
            ->where('status_id', 5)
            ->count();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id');
    }

    public function getMembersCountAttribute()
    {
        return $this->users()->count();
    }

    public function getTotalTimeAttribute()
    {
        return $this->users()
            ->join('user_contact_actions', 'users.id', '=', 'user_contact_actions.user_id')
            ->where('duration_seconds', '>', 0)
            ->sum('duration_seconds');
    }

    public function formatSeconds($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return sprintf("%dh %dm", $hours, $minutes);
        }
        return sprintf("%dm", $minutes);
    }
} 