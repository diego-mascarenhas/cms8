<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStatus extends Model
{
    public $timestamps = false;

    public const STATUS_BUDGET = 1;

    public const STATUS_BUDGETED = 2;

    public const STATUS_AUTHORIZED = 3;

    public const STATUS_SENT = 4;

    public const STATUS_APPROVED = 7;

    public const STATUS_WAITING_FOR_RESPONSE = 8;

    public const STATUS_IN_PROGRESS = 9;

    /**
     * Active / "in progress" statuses used by stats and counts.
     *
     * @return list<int>
     */
    public static function inProgressStatusIds(): array
    {
        return [
            self::STATUS_AUTHORIZED,
            self::STATUS_APPROVED,
            self::STATUS_WAITING_FOR_RESPONSE,
            self::STATUS_IN_PROGRESS,
        ];
    }

    /**
     * Statuses listed under dashboard "Ongoing Projects" (quote pipeline + active work).
     *
     * @return list<int>
     */
    public static function ongoingDashboardStatusIds(): array
    {
        return [
            self::STATUS_BUDGET,
            self::STATUS_BUDGETED,
            self::STATUS_AUTHORIZED,
            self::STATUS_APPROVED,
            self::STATUS_WAITING_FOR_RESPONSE,
            self::STATUS_IN_PROGRESS,
        ];
    }

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
    public function getLabelClassAttribute(): string
    {
        if (! empty($this->attributes['label_class']))
        {
            return (string) $this->attributes['label_class'];
        }

        return match ((int) $this->id)
        {
            self::STATUS_WAITING_FOR_RESPONSE => 'bg-label-warning',
            9 => 'bg-label-info',
            10 => 'bg-label-success',
            11 => 'bg-label-danger',
            default => 'bg-label-secondary',
        };
    }
}
