<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prompt extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'type_id', 'content', 'status'];

    public function type()
    {
        return $this->belongsTo(PromptType::class, 'type_id');
    }
}