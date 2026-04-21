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

        $collection = $query->orderBy('id')->get();

        if ($collection->isEmpty() && $enterpriseTypeId === 1)
        {
            self::ensureDefaultClientStatuses();
            $collection = self::query()->where('enterprise_type_id', 1)->orderBy('id')->get();
        }

        return $collection->map(function ($status)
        {
            return [
                'id' => $status->id,
                'name' => $status->name,
            ];
        });
    }

    /**
     * Ensure default enterprise statuses exist for clients (type_id = 1).
     */
    protected static function ensureDefaultClientStatuses(): void
    {
        $defaults = [
            ['id' => 1, 'name' => 'Inactivo', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-danger'],
            ['id' => 2, 'name' => 'Activo', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-success'],
        ];
        foreach ($defaults as $row)
        {
            self::query()->updateOrCreate(
                ['id' => $row['id']],
                $row,
            );
        }
    }
}
