<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';

    public $timestamps = false;
    
    protected $fillable = ['code', 'name'];

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';
    
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtolower($value);
    }
    
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'country', 'code');
    }

}