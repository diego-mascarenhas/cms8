<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Support\ContentsSectionCategoryData;
use Database\Seeders\DemoObaContentsSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoObaContentsSectionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_timeline_items_for_demo_team(): void
    {
        $user = User::factory()->create();
        Team::factory()->create([
            'name' => 'Demo',
            'user_id' => $user->id,
            'personal_team' => true,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'contents'],
            [
                'name' => 'Contents',
                'icon' => 'file-text',
                'description' => 'Test',
                'is_core' => true,
                'status' => 1,
            ],
        );

        config([
            'humano_pricing.plan_team_modules.assistant' => array_values(array_unique(array_merge(
                config('humano_pricing.plan_team_modules.assistant', []),
                ['contents'],
            ))),
        ]);

        $this->seed(DemoObaContentsSectionSeeder::class);

        $demoTeam = Team::query()->where('name', 'Demo')->firstOrFail();
        $timelineCount = Content::withoutGlobalScopes()
            ->where('team_id', $demoTeam->id)
            ->where('template', 'timeline_item')
            ->count();

        $this->assertSame(3, $timelineCount);

        $first = Content::withoutGlobalScopes()
            ->where('team_id', $demoTeam->id)
            ->where('template', 'timeline_item')
            ->where('order', 0)
            ->firstOrFail();

        $this->assertSame(
            'INICIO DE CONVERSACIONES MULTILATERALES',
            $first->getTranslatable('title'),
        );
        $this->assertSame(1987, (int) ($first->data['event_year'] ?? 0));
        $this->assertSame('/assets/images/timeline-1987.jpg', $first->data['image_url'] ?? null);

        $section = $first->sectionCategory;
        $this->assertNotNull($section);
        $this->assertSame(ContentsSectionCategoryData::DEMO_SLUG_OBA_ABOUT, $section->data['slug'] ?? null);
    }

    public function test_seeder_skips_contents_module_on_assistant_demo_plan(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'name' => 'Demo',
            'user_id' => $user->id,
            'personal_team' => true,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'contents'],
            [
                'name' => 'Contents',
                'icon' => 'file-text',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $team->enableModule('contents');
        $this->assertTrue($team->fresh()->hasModule('contents'));

        $this->seed(DemoObaContentsSectionSeeder::class);

        $this->assertFalse($team->fresh()->hasModule('contents'));
        $this->assertSame(
            0,
            Content::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('template', 'timeline_item')
                ->count(),
        );
    }
}
