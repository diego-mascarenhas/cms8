<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactPortfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'title',
        'description',
        'year',
        'notes',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
        'year' => 'integer'
    ];

    /**
     * Get the contact that owns the portfolio item
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the position from data JSON
     */
    public function getPositionAttribute()
    {
        return $this->data['position'] ?? null;
    }

    /**
     * Get the languages from data JSON
     */
    public function getLanguagesAttribute()
    {
        return $this->data['languages'] ?? [];
    }

    /**
     * Set the position in data JSON
     */
    public function setPositionAttribute($value)
    {
        $data = $this->data ?? [];
        $data['position'] = $value;
        $this->attributes['data'] = json_encode($data);
    }

    /**
     * Set the languages in data JSON
     */
    public function setLanguagesAttribute($value)
    {
        $data = $this->data ?? [];
        $data['languages'] = $value;
        $this->attributes['data'] = json_encode($data);
    }
}
