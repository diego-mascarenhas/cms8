<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Team;
use App\Models\Term;
use App\Models\TermTaxonomy;
use Illuminate\Database\Seeder;

/**
 * Seeds sample CMS content (pages, blog posts, categories and tags) for teams that
 * have the `cms` module enabled. Idempotent: keyed by team_id + post_type + post_name.
 */
class CmsDemoContentSeeder extends Seeder
{
    public static function seedForTeam(Team $team): void
    {
        PostTypeSeeder::seedForTeam($team);

        $authorId = $team->user_id;

        // --- Taxonomy terms (blog categories + tags) ---
        $categoryTtIds = [];
        foreach (['Noticias' => 'noticias', 'Tutoriales' => 'tutoriales', 'Empresa' => 'empresa'] as $name => $slug)
        {
            $categoryTtIds[$slug] = self::ensureTerm($team->id, $name, $slug, TermTaxonomy::TAXONOMY_CATEGORY);
        }
        $tagTtIds = [];
        foreach (['Producto' => 'producto', 'Guía' => 'guia', 'Anuncio' => 'anuncio'] as $name => $slug)
        {
            $tagTtIds[$slug] = self::ensureTerm($team->id, $name, $slug, TermTaxonomy::TAXONOMY_TAG);
        }

        // --- Pages ---
        $home = self::ensurePost($team->id, $authorId, 'page', 'inicio', [
            'post_title' => 'Inicio',
            'post_content' => '<h2>Bienvenido</h2><p>Esta es la página de inicio gestionada desde Humano CMS.</p>',
            'post_excerpt' => 'Página principal del sitio.',
            'menu_order' => 0,
        ]);

        self::ensurePost($team->id, $authorId, 'page', 'sobre-nosotros', [
            'post_title' => 'Sobre nosotros',
            'post_content' => '<h2>Quiénes somos</h2><p>Somos un equipo dedicado a ofrecer el mejor servicio.</p>',
            'post_excerpt' => 'Conoce a nuestro equipo.',
            'post_parent' => $home->id,
            'menu_order' => 1,
        ]);

        self::ensurePost($team->id, $authorId, 'page', 'contacto', [
            'post_title' => 'Contacto',
            'post_content' => '<h2>Contáctanos</h2><p>Escríbenos a hola@example.com.</p>',
            'post_excerpt' => 'Ponte en contacto con nosotros.',
            'menu_order' => 2,
        ]);

        self::ensurePost($team->id, $authorId, 'page', 'aviso-legal', [
            'post_title' => 'Aviso legal',
            'post_content' => '<p>Texto legal de ejemplo.</p>',
            'post_status' => Post::STATUS_DRAFT,
            'menu_order' => 3,
        ]);

        // --- Blog posts ---
        $post1 = self::ensurePost($team->id, $authorId, 'post', 'lanzamiento-de-nuestra-nueva-web', [
            'post_title' => 'Lanzamiento de nuestra nueva web',
            'post_content' => '<p>Nos complace anunciar el lanzamiento de nuestro nuevo sitio web.</p>',
            'post_excerpt' => 'Estrenamos sitio web.',
            'menu_order' => 0,
        ]);
        self::syncTerms($post1, [$categoryTtIds['noticias'], $tagTtIds['anuncio']]);

        $post2 = self::ensurePost($team->id, $authorId, 'post', 'como-empezar-con-el-cms', [
            'post_title' => 'Cómo empezar con el CMS',
            'post_content' => '<p>En este tutorial aprenderás a crear tu primera página.</p>',
            'post_excerpt' => 'Guía rápida para nuevos usuarios.',
            'menu_order' => 1,
        ]);
        self::syncTerms($post2, [$categoryTtIds['tutoriales'], $tagTtIds['guia']]);

        $post3 = self::ensurePost($team->id, $authorId, 'post', 'novedades-del-producto', [
            'post_title' => 'Novedades del producto',
            'post_content' => '<p>Estas son las últimas mejoras que hemos incorporado.</p>',
            'post_excerpt' => 'Resumen de las novedades.',
            'post_status' => Post::STATUS_DRAFT,
            'menu_order' => 2,
        ]);
        self::syncTerms($post3, [$categoryTtIds['empresa'], $tagTtIds['producto']]);
    }

    private static function ensureTerm(int $teamId, string $name, string $slug, string $taxonomy): int
    {
        $term = Term::withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $teamId, 'slug' => $slug],
            ['name' => $name],
        );

        $termTaxonomy = TermTaxonomy::withoutGlobalScopes()->firstOrCreate(
            ['term_id' => $term->id, 'taxonomy' => $taxonomy],
            ['team_id' => $teamId],
        );

        return $termTaxonomy->id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function ensurePost(int $teamId, ?int $authorId, string $type, string $slug, array $attributes): Post
    {
        $defaults = array_merge([
            'post_status' => Post::STATUS_PUBLISH,
            'post_author' => $authorId,
            'post_content' => '',
            'post_excerpt' => null,
            'post_parent' => 0,
            'menu_order' => 0,
        ], $attributes);

        return Post::withoutGlobalScopes()->updateOrCreate(
            ['team_id' => $teamId, 'post_type' => $type, 'post_name' => $slug],
            $defaults,
        );
    }

    /**
     * @param  array<int, int>  $termTaxonomyIds
     */
    private static function syncTerms(Post $post, array $termTaxonomyIds): void
    {
        $syncData = [];
        foreach (array_filter($termTaxonomyIds) as $id)
        {
            $syncData[$id] = ['team_id' => $post->team_id];
        }
        $post->termTaxonomies()->sync($syncData);
    }

    public function run(): void
    {
        $teams = Team::query()->get()->filter(fn (Team $team) => $team->hasModule('cms'));

        if ($teams->isEmpty())
        {
            $this->command?->warn('No teams with the cms module enabled. Enable it first, then re-run.');

            return;
        }

        foreach ($teams as $team)
        {
            self::seedForTeam($team);
            $this->command?->info("Seeded CMS demo content for team: {$team->name}");
        }
    }
}
