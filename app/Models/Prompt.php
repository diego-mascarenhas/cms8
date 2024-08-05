<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    protected $fillable = ['name', 'type_id', 'content', 'status'];

    public function type()
    {
        return $this->belongsTo(PromptType::class, 'type_id');
    }
}