<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactValoration extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'team_id',
        'name',
        'icon',
    ];

    protected $casts = [
        'id' => 'integer',
        'team_id' => 'integer',
    ];

    /**
     * Get the team that owns the valoration
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all contacts with this valoration
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'valoration_id');
    }

    /**
     * Get valorations for a specific team
     */
    public static function getOptions($teamId = null)
    {
        $teamId = $teamId ?? auth()->user()->currentTeam->id ?? 1;

        return self::where('team_id', $teamId)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get the default valorations
     */
    public static function getDefaults()
    {
        return [
            'Top',
            'Validada',
            'Interesante',
            'Lista negra',
            'En espera',
        ];
    }

    /**
     * Get available icons for selection
     */
    public static function getAvailableIcons()
    {
        return [
            '⭐' => 'Top/Estrella',
            '✅' => 'Validada/Check',
            '🕐' => 'Interesante/Reloj',
            '❌' => 'Lista negra/X',
            '👁️' => 'En espera/Ojo',
            '🔘' => 'Neutro/Círculo',
            '🎯' => 'Objetivo/Target',
            '💎' => 'Premium/Diamante',
            '🔥' => 'Urgente/Fuego',
            '📊' => 'Análisis/Gráfico',
            '🏆' => 'Excelente/Trofeo',
            '⚡' => 'Rápido/Rayo',
        ];
    }
}
