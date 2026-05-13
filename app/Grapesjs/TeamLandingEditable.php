<?php

namespace App\Grapesjs;

use App\Models\Team;
use Dotlogics\Grapesjs\App\Contracts\Editable;
use Illuminate\Support\Str;

/**
 * GrapesJS "editable" backed by {@see Team::getSetting}/{@see Team::setSetting} JSON (no pages table row).
 */
final class TeamLandingEditable implements Editable
{
    public const SETTING_KEY = 'landing_page_gjs_data';

    /**
     * @param  array<string, mixed>  $gjsData
     */
    public function __construct(
        private Team $team,
        private array $gjsData,
    ) {}

    public static function fromTeam(Team $team): self
    {
        $defaults = self::defaultGjsData();
        $stored = $team->getSetting(self::SETTING_KEY);
        if (! is_array($stored))
        {
            return new self($team, $defaults);
        }

        return new self($team, array_merge($defaults, $stored));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultGjsData(): array
    {
        return [
            'html' => '<body><section class="py-5"><div class="container"><h1>'.e(__('Welcome')).'</h1><p>'.e(__('Add blocks or edit this text, then save.')).'</p></div></section></body>',
            'components' => '[]',
            'styles' => '[]',
            'css' => '* { box-sizing: border-box; } body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }',
        ];
    }

    public function __get(string $name): mixed
    {
        if ($name === 'gjs_data')
        {
            return $this->getGjsDataAttribute(null);
        }

        $method = 'get'.Str::studly($name).'Attribute';

        if (method_exists($this, $method))
        {
            return $this->{$method}();
        }

        throw new \InvalidArgumentException("Unknown property [{$name}] on TeamLandingEditable.");
    }

    public function __isset(string $name): bool
    {
        if ($name === 'gjs_data')
        {
            return true;
        }

        $method = 'get'.Str::studly($name).'Attribute';

        return method_exists($this, $method);
    }

    public function getEditorPageTitleAttribute(): string
    {
        return __('Landing page').' — '.$this->team->name;
    }

    public function setGjsDataAttribute($value): void
    {
        $this->gjsData = is_array($value) ? $value : [];
    }

    public function getGjsDataAttribute($value): array
    {
        unset($value);

        return $this->gjsData;
    }

    public function getStyleSheetLinksAttribute(): array
    {
        return [];
    }

    public function getScriptLinksAttribute(): array
    {
        return [];
    }

    public function getComponentsAttribute(): array
    {
        $decoded = json_decode($this->gjsData['components'] ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getStylesAttribute(): array
    {
        $decoded = json_decode($this->gjsData['styles'] ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getHtmlAttribute(): string
    {
        return (string) ($this->gjsData['html'] ?? '');
    }

    public function getCssAttribute(): string
    {
        return (string) ($this->gjsData['css'] ?? '');
    }

    public function getAssetsAttribute(): array
    {
        return [];
    }

    public function getStoreUrlAttribute(): string
    {
        return route('page.team-landing-editor.store');
    }

    public function getTemplatesUrlAttribute(): ?string
    {
        return route('laravel-grapesjs.templates');
    }
}
