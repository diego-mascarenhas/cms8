<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactLanguageVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'source_language_code',
        'target_language_code',
        'proficiency_level',
        'is_certified',
        'notes',
    ];

    protected $casts = [
        'is_certified' => 'boolean',
        'proficiency_level' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('contact', function ($query) {
                    $query->where('team_id', auth()->user()->currentTeam->id);
                });
            }
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sourceLanguage()
    {
        return $this->belongsTo(LanguageVariant::class, 'source_language_code', 'code');
    }

    public function targetLanguage()
    {
        return $this->belongsTo(LanguageVariant::class, 'target_language_code', 'code');
    }
}
