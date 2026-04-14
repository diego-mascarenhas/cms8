<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Content;
use App\Models\ContentFieldConfig;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Support\ContentsSectionCategoryData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent seed for Demo team: API token, "OBA - Acerca de" contents section category,
 * field configs, and timeline items. Safe to run if TeamDemoSeeder was skipped or DB is old.
 *
 * Usage: php artisan db:seed --class=DemoObaContentsSectionSeeder
 */
class DemoObaContentsSectionSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if (! $team)
        {
            $this->command?->error('No team named "Demo" found. Create it or run UserSeeder / TeamDemoSeeder first.');

            return;
        }

        $this->ensureContentsModuleEnabledForTeam($team);
        $this->seedDemoTeamApiToken($team);
        $this->seedObaAboutDynamicContent($team);
    }

    private function ensureContentsModuleEnabledForTeam(Team $team): void
    {
        $module = Module::query()->where('key', 'contents')->first();
        if (! $module)
        {
            $this->command?->warn('Contents module not found in modules table. Run ModuleSeeder.');

            return;
        }

        DB::table('module_team')->updateOrInsert(
            [
                'module_id' => $module->id,
                'team_id' => $team->id,
            ],
            [
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->command?->info('✅ Contents module enabled for Demo team');
    }

    private function seedDemoTeamApiToken(Team $team): void
    {
        if ($team->getSetting('api_token_hash'))
        {
            $this->command?->info('🔑 Team API token already set — skipping (use Team Settings → API Tokens to rotate).');

            return;
        }

        $this->command?->info('🔑 Seeding Demo team API token (team contents / integrations)...');

        $plain = env('DEMO_TEAM_API_TOKEN', 'oba-demo-humano-team-token-local-only-2026');
        $tokenHash = hash('sha256', $plain);

        $team->setSetting('api_token_hash', $tokenHash, [
            'group' => 'api',
            'is_encrypted' => true,
        ]);
        $team->setSetting('api_token_plain', $plain, [
            'group' => 'api',
            'is_encrypted' => true,
        ]);
        $team->setSetting('api_token_created_at', now()->toDateTimeString(), [
            'group' => 'api',
            'is_encrypted' => false,
        ]);

        $this->command?->info('✅ Demo API token configured (override with DEMO_TEAM_API_TOKEN in .env)');
    }

    private function seedObaAboutDynamicContent(Team $team): void
    {
        $this->command?->info('📄 Seeding OBA About section (contents module + timeline)...');

        $contentsModule = Module::where('key', 'contents')->first();
        if (! $contentsModule)
        {
            $this->command?->warn('⚠️  Contents module not found. Skipping OBA About seed.');

            return;
        }

        $sectionCategory = Category::updateOrCreate(
            [
                'team_id' => $team->id,
                'module_id' => $contentsModule->id,
                'name' => 'OBA - Acerca de',
            ],
            [
                'description' => 'Acerca de OBA — timeline y flags de builder para sitios externos.',
                'parent_id' => null,
                'order' => 0,
                'status' => true,
                'data' => ContentsSectionCategoryData::obaAboutSection(),
            ],
        );

        $fieldDefinitions = [
            [
                'field_key' => 'event_year',
                'field_type' => 'number',
                'field_label' => 'Año del evento',
                'order' => 0,
            ],
            [
                'field_key' => 'image_url',
                'field_type' => 'url',
                'field_label' => 'URL de imagen',
                'order' => 1,
            ],
        ];

        foreach ($fieldDefinitions as $def)
        {
            ContentFieldConfig::updateOrCreate(
                [
                    'team_id' => $team->id,
                    'section_category_id' => $sectionCategory->id,
                    'field_key' => $def['field_key'],
                ],
                [
                    'field_type' => $def['field_type'],
                    'field_label' => $def['field_label'],
                    'field_options' => null,
                    'is_active' => true,
                    'order' => $def['order'],
                    'required' => false,
                ],
            );
        }

        $admin = User::query()->where('email', 'admin@humano.app')->first();
        $userId = $admin?->id ?? 1;

        $timelineItems = [
            [
                'order' => 0,
                'title' => ['es' => 'INICIO DE CONVERSACIONES MULTILATERALES'],
                'content' => ['es' => 'Representantes de Argentina, Brasil, Paraguay, Chile y Uruguay iniciaron conversaciones para crear una organización que uniera a los bomberos de América.'],
                'data' => [
                    'event_year' => 1987,
                    'image_url' => '/assets/timeline-1987.jpg',
                ],
            ],
            [
                'order' => 1,
                'title' => ['es' => 'ACTO DE COMPROMISO'],
                'content' => ['es' => 'Argentina, Paraguay y Uruguay firmaron un acta de compromiso para la creación de la Organización de Bomberos Americanos.'],
                'data' => [
                    'event_year' => 2005,
                    'image_url' => '/assets/timeline-2005-compromiso.jpg',
                ],
            ],
            [
                'order' => 2,
                'title' => ['es' => 'FUNDACIÓN ORGANIZACIÓN BOMBEROS AMERICANOS'],
                'content' => ['es' => 'Se firmó la carta fundacional de la OBA con los miembros fundadores: Argentina, Caracas (Venezuela), Paraguay, Santiago de Chile (Chile) y Uruguay.'],
                'data' => [
                    'event_year' => 2005,
                    'image_url' => '/assets/timeline-2005-fundacion.jpg',
                ],
            ],
        ];

        foreach ($timelineItems as $item)
        {
            Content::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'section_category_id' => $sectionCategory->id,
                    'template' => 'timeline_item',
                    'order' => $item['order'],
                ],
                [
                    'category_id' => null,
                    'status' => 3,
                    'featured' => false,
                    'featured_slide' => false,
                    'featured_modal' => false,
                    'title' => $item['title'],
                    'subtitle' => null,
                    'url' => null,
                    'content' => $item['content'],
                    'data' => $item['data'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        $this->command?->info('✅ OBA About section seeded (category id '.$sectionCategory->id.', timeline items)');
    }
}
