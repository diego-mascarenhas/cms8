<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DataTableFormatter
{
    public static function showLink(
        Model $model,
        string $routeName,
        string $label,
        string $ability = 'view',
        array $routeParameters = [],
        string $linkClass = 'fw-medium text-body text-truncate',
        ?string $permission = null,
    ): string {
        $escapedLabel = e($label);

        $canView = $permission !== null
            ? auth()->check() && auth()->user()->can($permission)
            : Gate::allows($ability, $model);

        if (! $canView)
        {
            return '<span class="'.$linkClass.'">'.$escapedLabel.'</span>';
        }

        $parameters = $routeParameters !== [] ? $routeParameters : [$model];
        $url = e(route($routeName, $parameters));

        return '<a href="'.$url.'" class="'.$linkClass.'">'.$escapedLabel.'</a>';
    }

    public static function link(
        string $url,
        string $label,
        string $linkClass = 'fw-medium text-body text-truncate',
    ): string {
        return '<a href="'.e($url).'" class="'.$linkClass.'">'.e($label).'</a>';
    }

    public static function nameColumn(string $primaryHtml, ?string $subtitle = null): string
    {
        $subtitleHtml = $subtitle !== null && $subtitle !== ''
            ? e($subtitle)
            : '&nbsp;';

        return '<div class="d-flex flex-column">'
            .$primaryHtml
            .'<small class="text-muted">'.$subtitleHtml.'</small>'
            .'</div>';
    }
}
