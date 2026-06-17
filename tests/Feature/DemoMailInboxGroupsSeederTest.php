<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Team;
use App\Services\Mail\MailInboxService;
use Database\Seeders\DemoMailInboxGroupsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMailInboxGroupsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_grouped_demo_emails(): void
    {
        $team = Team::factory()->create(['name' => 'Demo']);

        $this->seed(DemoMailInboxGroupsSeeder::class);

        $this->assertGreaterThanOrEqual(10, Email::query()->where('team_id', $team->id)->count());

        $groups = app(MailInboxService::class)->senderGroups($team, 'inbox', '');

        $this->assertGreaterThanOrEqual(4, $groups->count());

        $idoneoGroup = $groups->firstWhere('sender_key', 'contabilidad@idoneo.es');
        $this->assertNotNull($idoneoGroup);
        $this->assertGreaterThanOrEqual(3, $idoneoGroup['count']);
    }
}
