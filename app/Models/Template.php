<?php

namespace App\Models;

// Will need to be adjusted or made configurable
use Dotlogics\Grapesjs\App\Contracts\Editable;
use Dotlogics\Grapesjs\App\Traits\EditableTrait;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Template extends Model implements Editable
{
    use EditableTrait;
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'templates';

    protected $fillable = ['name', 'gjs_data', 'status_id', 'team_id'];

    protected $casts = [
        'gjs_data' => 'array',
        'status_id' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function ($model)
        {
            if (! $model->team_id && auth()->check())
            {
                $model->team_id = auth()->user()->currentTeam->id;
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public static function getOptions()
    {
        return self::all()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKey()
    {
        return $this->getHashedId();
    }

    /**
     * Hash the ID for public URLs
     *
     * @return string
     */
    public function getHashedId()
    {
        return Crypt::encryptString($this->id);
    }

    /**
     * Find a template by its hashed ID
     *
     * @param  string  $hashedId
     * @return Template|null
     */
    public static function findByHash($hashedId)
    {
        try
        {
            $id = Crypt::decryptString($hashedId);

            return self::find($id);
        } catch (DecryptException $e)
        {
            return null;
        }
    }

    /**
     * Get the current team ID for the template
     *
     * @return int|null
     */
    public function getTeamId()
    {
        return auth()->user()->currentTeam->id ?? null;
    }

    public function getComponentsAttribute(): array
    {
        $value = $this->gjs_data['components'] ?? [];

        return $this->normalizeArrayValue($value);
    }

    public function getStylesAttribute(): array
    {
        $value = $this->gjs_data['styles'] ?? [];

        return $this->normalizeArrayValue($value);
    }

    public function getCssAttribute(): string
    {
        $value = $this->gjs_data['css'] ?? '';

        return is_string($value) ? $value : '';
    }

    public function getHtmlAttribute(): string
    {
        $value = $this->gjs_data['html'] ?? '';

        return is_string($value) ? $value : '';
    }

    public function getEditorJsonAttribute(): mixed
    {
        return $this->gjs_data['editor_json'] ?? null;
    }

    /**
     * Merge html/css/editor_json into gjs_data while preserving other keys.
     *
     * @param  array{html?: string|null, css?: string|null, editor_json?: mixed}  $payload
     */
    public function mergeGjsData(array $payload): void
    {
        $gjs = is_array($this->gjs_data) ? $this->gjs_data : [];

        if (array_key_exists('html', $payload) && $payload['html'] !== null)
        {
            $gjs['html'] = (string) $payload['html'];
        }

        if (array_key_exists('css', $payload) && $payload['css'] !== null)
        {
            $gjs['css'] = (string) $payload['css'];
        }

        if (array_key_exists('editor_json', $payload) && $payload['editor_json'] !== null)
        {
            $gjs['editor_json'] = $payload['editor_json'];
        }

        $this->gjs_data = $gjs;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status_id' => (bool) $this->status_id,
            'html' => $this->html,
            'css' => $this->css,
            'editor_json' => $this->editor_json,
            'hashed_id' => $this->getHashedId(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeArrayValue(mixed $value): array
    {
        if (is_array($value))
        {
            return $value;
        }

        if (is_string($value) && trim($value) !== '')
        {
            $decoded = json_decode($value, true);
            if (is_array($decoded))
            {
                return $decoded;
            }
        }

        return [];
    }
}
