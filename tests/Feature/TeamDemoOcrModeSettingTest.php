<?php

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\TeamDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class TeamDemoOcrModeSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_presentation_settings_seed_hybrid_documents_ocr_mode(): void
    {
        $team = Team::factory()->create();

        $command = $this->createMock(Command::class);
        $command->method('info');

        $seeder = new TeamDemoSeeder;
        $commandProperty = new ReflectionProperty(Seeder::class, 'command');
        $commandProperty->setAccessible(true);
        $commandProperty->setValue($seeder, $command);

        $method = new ReflectionMethod(TeamDemoSeeder::class, 'configureDemoPresentationSettings');
        $method->invoke($seeder, $team);

        $this->assertSame('hybrid', $team->fresh()->getSetting('documents_ocr_mode'));
    }
}
