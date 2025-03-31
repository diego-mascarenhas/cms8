<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStatus extends Model
{
    protected $fillable = ['name', 'color', 'order'];
    public $timestamps = false;

    public static function getOptions()
    {
        return self::orderBy('order')->get()->map(function ($status) {
            return [
                'id' => $status->id,
                'name' => __("task_status.{$status->name}"),
            ];
        });
    }

    public function getLabelClassAttribute()
    {
        return match($this->name) {
            'TO_DO' => 'bg-label-secondary',
            'IN_PROGRESS' => 'bg-label-primary',
            'REVIEW' => 'bg-label-warning',
            'DONE' => 'bg-label-success',
            default => 'bg-label-info',
        };
    }
} 