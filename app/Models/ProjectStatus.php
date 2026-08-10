<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStatus extends Model
{
    public $timestamps = false;

    public const STATUS_BUDGET = 1;

    public const STATUS_BUDGETED = 2;

    public const STATUS_AUTHORIZED = 3;

    public const STATUS_APPROVED = 7;

    public const STATUS_WAITING_FOR_RESPONSE = 8;

    /**
     * @var list<string>
     */
    protected $appends = [
        'translated_name',
    ];

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
    public function getTranslatedNameAttribute(): string
    {
        if ($this->name === null || $this->name === '')
        {
            return '';
        }

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
