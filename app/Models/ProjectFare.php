<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFare extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'fare_id',
        'source_language_code',
        'target_language_code',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the project that owns this fare
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the fare that belongs to this project
     */
    public function fare()
    {
        return $this->belongsTo(Fare::class);
    }

    /**
     * Get the source language variant
     */
    public function sourceLanguage()
    {
        return $this->belongsTo(LanguageVariant::class, 'source_language_code', 'code');
    }

    /**
     * Get the target language variant
     */
    public function targetLanguage()
    {
        return $this->belongsTo(LanguageVariant::class, 'target_language_code', 'code');
    }

    /**
     * Get the formatted language combination
     */
    public function getLanguageCombinationAttribute()
    {
        return $this->sourceLanguage->name . ' → ' . $this->targetLanguage->name;
    }

    /**
     * Get the formatted quantity with unit
     */
    public function getFormattedQuantityAttribute()
    {
        return $this->quantity . ($this->unit ? ' ' . $this->unit : '');
    }
} 