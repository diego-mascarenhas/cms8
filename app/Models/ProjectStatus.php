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

    public const STATUS_RECEIVED = 5;

    public const STATUS_APPROVED = 7;

    public const STATUS_WAITING_FOR_RESPONSE = 8;

    public const STATUS_IN_PROGRESS = 9;

    public const STATUS_FINISHED = 10;

    public const STATUS_TO_INVOICE = 11;

    public const STATUS_INVOICED = 12;

    public const STATUS_NOT_APPROVED = 13;

    public const STATUS_BONIFIED = 14;

    /**
     * Statuses allowed after a budget is approved (status-only changes).
     *
     * @return list<int>
     */
    public static function allowedAfterApprovalStatusIds(): array
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_WAITING_FOR_RESPONSE,
            self::STATUS_IN_PROGRESS,
            self::STATUS_FINISHED,
            self::STATUS_TO_INVOICE,
            self::STATUS_INVOICED,
            self::STATUS_NOT_APPROVED,
            self::STATUS_BONIFIED,
        ];
    }

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
     * CASE branches for sort=relevance. Lower rank is shown first:
     * active work, then billing, then quotes.
     */
    public static function relevanceOrderSql(): string
    {
        $groups = [
            0 => self::inProgressStatusIds(),
            1 => [self::STATUS_FINISHED, self::STATUS_TO_INVOICE],
            2 => [self::STATUS_BUDGETED],
            3 => [self::STATUS_BUDGET],
        ];

        $clauses = [];

        foreach ($groups as $rank => $ids)
        {
            foreach ($ids as $id)
            {
                $clauses[] = 'WHEN '.(int) $id.' THEN '.(int) $rank;
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Finished work that no longer belongs in the active project list.
     *
     * @return list<int>
     */
    public static function closedStatusIds(): array
    {
        return [
            self::STATUS_FINISHED,
            self::STATUS_INVOICED,
            self::STATUS_NOT_APPROVED,
            self::STATUS_BONIFIED,
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

        return $query->orderBy('id')->get()->map(function ($status)
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
