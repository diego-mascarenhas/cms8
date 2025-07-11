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
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'year' => 'integer',
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
        $languages = $this->data['languages'] ?? [];

        // If it's stored as language pairs with source/target
        if (is_array($languages) && ! empty($languages) && isset($languages[0]['source'])) {
            return $languages;
        }

        // If it's stored as simple array, convert to pairs format for backwards compatibility
        if (is_array($languages) && ! empty($languages) && is_string($languages[0])) {
            return $languages;
        }

        return [];
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
