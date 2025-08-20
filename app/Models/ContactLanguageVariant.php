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
		static::addGlobalScope('team', function (Builder $builder)
		{
			if (auth()->check())
			{
				$builder->whereHas('contact', function ($query)
				{
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

	/**
	 * Get language combinations with less than the specified number of collaborators
	 */
	public static function getCombinationsWithFewCollaborators($maxCount = 10, $limit = 15, $teamId = null)
	{
		$teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

		return static::select([
			'source_language_code',
			'target_language_code',
		])
			->selectRaw('COUNT(DISTINCT contact_id) as collaborator_count')
			->with(['sourceLanguage', 'targetLanguage'])
			->whereHas('contact', function ($query) use ($teamId)
			{
				$query->where('team_id', $teamId);
			})
			->whereRaw('SUBSTRING_INDEX(source_language_code, "-", 1) != SUBSTRING_INDEX(target_language_code, "-", 1)') // Exclude same language combinations (comparing base language codes)
			->groupBy('source_language_code', 'target_language_code')
			->havingRaw('COUNT(DISTINCT contact_id) < ?', [$maxCount])
			->orderBy('collaborator_count', 'asc')
			->limit($limit)
			->get()
			->map(function ($combination)
			{
				// Get language variant instances for this combination
				$sourceVariant = LanguageVariant::where('code', $combination->source_language_code)->first();
				$targetVariant = LanguageVariant::where('code', $combination->target_language_code)->first();

				return [
					'source_code' => $combination->source_language_code,
					'target_code' => $combination->target_language_code,
					'source_name' => $sourceVariant ? $sourceVariant->name : $combination->source_language_code,
					'target_name' => $targetVariant ? $targetVariant->name : $combination->target_language_code,
					'source_flag' => $sourceVariant ? strtolower($sourceVariant->country_code) : strtolower(explode('-', $combination->source_language_code)[1] ?? 'us'),
					'target_flag' => $targetVariant ? strtolower($targetVariant->country_code) : strtolower(explode('-', $combination->target_language_code)[1] ?? 'us'),
					'count' => $combination->collaborator_count,
				];
			});
	}
}
