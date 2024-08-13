<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptType extends Model
{
    public $timestamps = false;
    protected $fillable = ['name'];

    public function prompts()
    {
        return $this->hasMany(Prompt::class, 'type_id');
    }

    public static function getOptions()
    {
        return self::all()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }
}