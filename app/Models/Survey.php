<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'title',
        'subtitle',
        'code',
        'expires_at',
        'is_active',
        'schema_json',
        'certificate_url',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'is_active' => 'boolean',
        'schema_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Vote counts per option. Names are included only when the field is not anonymous.
     *
     * @return list<array<string, mixed>>
     */
    public function fieldResults(): array
    {
        $fields = $this->schema_json['fields'] ?? [];
        $responses = $this->relationLoaded('responses')
            ? $this->responses->loadMissing('user')
            : $this->responses()->with('user')->get();

        $results = [];

        foreach ($fields as $field)
        {
            if (! is_array($field))
            {
                continue;
            }

            $anonymous = (bool) ($field['anonymous'] ?? false);
            $counts = [];
            $voters = [];

            foreach ($field['options'] ?? [] as $option)
            {
                $counts[(string) ($option['value'] ?? '')] = 0;
            }

            foreach ($responses as $response)
            {
                $answers = $response->payload_json['answers'] ?? [];
                $value = $answers[$field['key']] ?? null;

                if ($value === null || $value === '')
                {
                    continue;
                }

                $value = (string) $value;

                if (! array_key_exists($value, $counts))
                {
                    $counts[$value] = 0;
                }

                $counts[$value]++;

                if (! $anonymous)
                {
                    $voters[$value][] = $response->user?->name
                        ?: $response->user?->email;
                }
            }

            $total = array_sum($counts);
            $options = [];

            foreach ($field['options'] ?? [] as $option)
            {
                $value = (string) ($option['value'] ?? '');
                $count = $counts[$value] ?? 0;
                $options[] = [
                    'label' => (string) ($option['label'] ?? ''),
                    'value' => $value,
                    'count' => $count,
                    'percent' => $total > 0 ? round($count * 100 / $total, 1) : 0,
                    'voters' => $voters[$value] ?? [],
                ];
            }

            $results[] = [
                'key' => (string) ($field['key'] ?? ''),
                'label' => (string) ($field['label'] ?? ''),
                'anonymous' => $anonymous,
                'for_certificate' => (bool) ($field['for_certificate'] ?? false),
                'total' => $total,
                'options' => $options,
            ];
        }

        return $results;
    }
}
