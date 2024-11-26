<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name'];

    public function enterprises()
    {
        return $this->hasMany(Enterprise::class);
    }
    
    public function enterpriseType()
    {
        return $this->belongsTo(EnterpriseType::class);
    }

    public function list60s()
    {
        return $this->hasMany(List60::class, 'type_id');
    }
}