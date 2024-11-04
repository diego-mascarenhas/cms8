<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\UserContactAction;
use Carbon\Carbon;

class Contact extends Model
{
	use HasFactory;
	use SoftDeletes;

	protected $fillable = [
		'team_id',
		'user_id',
		'name',
		'source_id',
		'birthday',
		'profile',
		'engagment',
		'country',
		'language',
		'creator_id',
		'responsible_id',
		'data',
		'status_id',
	];

	protected $casts = [
		'data' => 'object',
		'birthday' => 'date',
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
	}

	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'creator_id');
	}

	public function responsible()
	{
		return $this->belongsTo(User::class, 'responsible_id');
	}

	public function enterprise()
	{
		return $this->hasOne(Enterprise::class, 'responsible_id');
	}

	public function country()
	{
		return $this->belongsTo(Country::class, 'country', 'id');
	}

	public function language()
	{
		return $this->belongsTo(Language::class, 'language', 'code');
	}

	public function sentimentHistories()
	{
		return $this->hasMany(ContactSentimentHistory::class);
	}

	public function currentSentiment()
	{
		return $this->hasOne(ContactSentimentHistory::class)->latest();
	}


	public function list60s()
	{
		return $this->hasMany(List60::class, 'contact_id');
	}

	public function status()
	{
		return $this->belongsTo(ContactStatus::class);
	}

	public function getStatusLabelAttribute()
	{
		if ($this->status)
		{
			return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->name . '</span>';
		}
		return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
	}

	public static function getContactStats($teamId)
	{
		$statusLabels = [
			1 => 'Leads',
			2 => 'FollowUp',
			5 => 'Clients',
			6 => 'Finished',
		];

		$contactStats = self::where('team_id', $teamId)
			->whereIn('status_id', array_keys($statusLabels))
			->get()
			->groupBy('status_id')
			->map(function ($group)
			{
				return $group->count();
			});

		$totalContacts = $contactStats->sum();

		$data = ['totalContacts' => $totalContacts];
		foreach ($statusLabels as $statusId => $label)
		{
			$count = $contactStats[$statusId] ?? 0;
			$percentage = $totalContacts > 0 ? round(($count / $totalContacts) * 100, 2) : 0;
			$data["total$label"] = $count;
			$data[lcfirst($label) . 'Percentage'] = $percentage;
		}

		$defaultData = [
			'totalContacts' => 0,
			'totalLeads' => 0,
			'leadsPercentage' => 0,
			'totalClients' => 0,
			'clientsPercentage' => 0,
			'totalFollowUp' => 0,
			'followUpPercentage' => 0,
			'totalFinished' => 0,
			'finishedPercentage' => 0,
		];

		$finalData = array_merge($defaultData, $data);

		return $finalData;
	}

	public function actions()
	{
		return $this->hasMany(UserContactAction::class, 'contact_id');
	}

	public function calculateCurrentActionSeconds()
	{
		$latestAction = UserContactAction::where('contact_id', $this->id)
			->whereNull('end_time')
			->latest('start_time')
			->first();

		if (!$latestAction)
		{
			return 0;
		}

		$startTime = $latestAction->start_time;
		$endTime = Carbon::now();

		return $endTime->diffInSeconds($startTime);
	}

	public function calculateTotalAccumulatedSeconds()
	{
		$completedActions = UserContactAction::where('contact_id', $this->id)
			->whereNotNull('end_time')
			->get();

		$totalSeconds = 0;

		foreach ($completedActions as $action)
		{
			$totalSeconds += Carbon::parse($action->end_time)->diffInSeconds($action->start_time);
		}

		$currentActionSeconds = $this->calculateCurrentActionSeconds();
		$totalSeconds += $currentActionSeconds;

		return $totalSeconds;
	}

	public static function getTotalTeamMinutes()
	{
		$totalTeamSeconds = self::sum('duration_seconds');
		return round($totalTeamSeconds / 60);
	}

	public static function getTotalTeamTime()
	{
		$totalMinutes = self::getTotalTeamMinutes();
		$hours = floor($totalMinutes / 60);
		$minutes = $totalMinutes % 60;

		return [
			'hours' => $hours,
			'minutes' => $minutes,
		];
	}

	public function sources()
	{
		return $this->belongsToMany(Source::class, 'contact_sources')->withPivot('value');
	}

	public function primarySource()
	{
		return $this->belongsTo(Source::class, 'source_id');
	}

	public function getSourcesIconsHtmlAttribute()
	{
		if ($this->sources->isEmpty()) {
			return 'Sin especificar';
		}

		$html = '<div class="social-icons">';
		
		foreach ($this->sources as $source) {
			$isPrimary = $source->id === $this->source_id;
			$title = $isPrimary ? 'Primary Source: ' . $source->name : $source->name;
			$iconClass = in_array($source->icon, ['fa-envelope', 'fa-phone']) ? "fas {$source->icon} fa-lg" : "fab {$source->icon} fa-lg";
			$url = $source->base_url . $source->pivot->value;

			$html .= sprintf(
				'<a href="%s" target="_blank" class="%s" title="%s"><i class="%s" style="color: %s;"></i></a>',
				$url,
				$isPrimary ? 'primary-source' : '',
				$title,
				$iconClass,
				$source->color
			);
		}

		$html .= '</div><style>
			.social-icons {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				align-items: center;
				justify-content: center;
				min-width: 80px;
				width: 100%;
			}
			.social-icons a {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 20px;
			}
		</style>';

		return $html;
	}

	public function getEmailAttribute()
	{
		$emailSource = $this->sources()
			->where('source_id', 1)
			->first();

		return $emailSource ? $emailSource->pivot->value : null;
	}
	public function getPhoneAttribute()
	{
		$phoneSource = $this->sources()
			->where('source_id', 2)
			->first();

		return $phoneSource ? $phoneSource->pivot->value : null;
	}

	public function enterprises(): BelongsToMany
	{
		return $this->belongsToMany(Enterprise::class, 'contact_enterprise')
					->withPivot('position')
					->withTimestamps();
	}
}
