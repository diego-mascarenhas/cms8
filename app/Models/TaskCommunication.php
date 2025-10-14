<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'recipients',
        'method',
        'subject',
        'message',
        'response_token',
        'response',
        'response_at',
        'sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'sent_at' => 'datetime',
        'response_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
