<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStatus extends Model
{
    public $timestamps = false;

    public static function getOptions()
    {
        $query = self::query();

        return $query->get()->map(function ($status) {
            return [
                'id' => $status->id,
                'name' => $status->name,
            ];
        });
    }
}
