<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseStatus extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function status()
    {
        return $this->hasMany(Enterprise::class, 'status_id');
    }

    public function list60s()
    {
        return $this->hasMany(List60::class, 'status_id');
    }

    public static function getOptions($enterpriseTypeId = null)
    {
        $query = self::query();

        if (! is_null($enterpriseTypeId))
        {
            $query->where('enterprise_type_id', $enterpriseTypeId);
        }

        return $query->get()->map(function ($status)
        {
            return [
                'id' => $status->id,
                'name' => $status->name,
            ];
        });
    }
}
