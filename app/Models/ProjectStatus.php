<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStatus extends Model
{
    public $timestamps = false;

    public static function getOptions()
    {
        $query = self::query();

        return $query->get()->map(function ($status)
        {
            return [
                'id' => $status->id,
                'name' => $status->translated_name,
            ];
        });
    }

    /**
     * Get the translated status name
     */
    public function getTranslatedNameAttribute()
    {
        return __("project_status.{$this->name}");
    }

    /**
     * Get the appropriate label class for the status
     */
    public function getLabelClassAttribute()
    {
        switch ($this->id)
        {
            case 8: // PENDING
                return 'bg-label-warning';
            case 9: // IN_PROGRESS
                return 'bg-label-info';
            case 10: // COMPLETED
                return 'bg-label-success';
            case 11: // CANCELED
                return 'bg-label-danger';
            default:
                return 'bg-label-secondary';
        }
    }
}
