<?php

namespace Database\Seeders;

use App\Models\PostType;
use App\Models\Team;
use App\Models\TermTaxonomy;
use Illuminate\Database\Seeder;

class PostTypeSeeder extends Seeder
{
    /**
     * Core post types registered for every team, mirroring WordPress defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            [
                'name' => 'post',
                'label' => 'Entradas',
                'label_singular' => 'Entrada',
                'icon' => 'article',
                'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'seo'],
                'hierarchical' => false,
                'has_archive' => true,
                'public' => true,
                'taxonomies' => [TermTaxonomy::TAXONOMY_CATEGORY, TermTaxonomy::TAXONOMY_TAG],
                'menu_order' => 1,
            ],
            [
                'name' => 'page',
                'label' => 'Páginas',
                'label_singular' => 'Página',
                'icon' => 'file-description',
                'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'seo'],
                'hierarchical' => true,
                'has_archive' => false,
                'public' => true,
                'taxonomies' => [],
                'menu_order' => 2,
            ],
            [
                'name' => 'attachment',
                'label' => 'Medios',
                'label_singular' => 'Medio',
                'icon' => 'photo',
                'supports' => ['title', 'custom-fields'],
                'hierarchical' => false,
                'has_archive' => false,
                'public' => true,
                'taxonomies' => [],
                'menu_order' => 3,
            ],
        ];
    }

    /**
     * Register the core post types for a single team (idempotent).
     */
    public static function seedForTeam(Team $team): void
    {
        foreach (self::defaults() as $definition)
        {
            PostType::withoutGlobalScopes()->updateOrCreate(
                ['team_id' => $team->id, 'name' => $definition['name']],
                array_merge($definition, ['team_id' => $team->id]),
            );
        }
    }

    public function run(): void
    {
        Team::query()->each(function (Team $team)
        {
            self::seedForTeam($team);
        });
    }
}
