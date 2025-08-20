<?php

namespace App\Traits;

trait HasSourceIcons
{
	public function getSourcesIconsHtmlAttribute()
	{
		$sourcesHtml = $this->sources->map(function ($source)
		{
			$isPrimary = $source->id === $this->source_id;
			$style = $isPrimary ? 'font-size: 1.2em; margin-right: 12px; white-space: nowrap;' : 'margin-right: 12px; white-space: nowrap;';
			$title = $isPrimary ? 'Primary Source: '.$source->name : $source->name;

			$iconClass = in_array($source->icon, ['fa-envelope', 'fa-phone']) ? "fas {$source->icon} fa-lg" : "fab {$source->icon} fa-lg";

			$value = $source->pivot->value;
			$url = $source->base_url.$value;

			return sprintf(
				'<a href="%s" target="_blank" style="%s"><i class="%s" style="color: %s;" title="%s"></i></a>',
				$url,
				$style,
				$iconClass,
				$source->color,
				$title,
			);
		});

		return $sourcesHtml->isEmpty() ? 'Sin especificar' : $sourcesHtml->implode('');
	}

	public function getIconClassAttribute()
	{
		return in_array($this->icon, ['fa-envelope', 'fa-phone']) ? 'fas' : 'fab';
	}
}
