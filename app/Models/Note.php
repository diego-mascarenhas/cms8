<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'module_id',
        'reference',
        'name',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getModuleNameAttribute()
    {
        $modules = [
            1 => 'projects',
            2 => 'contacts',
            3 => 'clients',
            // Add more modules as needed
        ];

        return $modules[$this->module_id] ?? 'unknown';
    }
} 