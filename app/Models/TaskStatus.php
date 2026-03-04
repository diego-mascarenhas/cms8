<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStatus extends Model
{
    protected $fillable = ['name', 'color', 'label_class', 'order'];

    public $timestamps = false;

    public static function getOptions()
    {
        return self::orderBy('order')->get()->map(function ($status)
        {
            return [
                'id' => $status->id,
                'name' => $status->translated_name,
            ];
        });
    }

    public function getTranslatedNameAttribute()
    {
        return __("task_status.{$this->name}");
    }

    public function getLabelClassAttribute()
    {
        return match ($this->name)
        {
            'TO_DO' => 'bg-label-secondary',
            'IN_PROGRESS' => 'bg-label-primary',
            'REVIEW' => 'bg-label-warning',
            'DONE' => 'bg-label-success',
            default => 'bg-label-info',
        };
    }
}
