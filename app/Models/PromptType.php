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
}