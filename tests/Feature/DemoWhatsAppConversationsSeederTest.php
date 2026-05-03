<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoWhatsAppConversationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWhatsAppConversationsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_whatsapp_demo_rows_for_demo_team(): void
    {
        $user = User::factory()->create();
        Team::factory()->create([
            'name' => 'Demo',
            'user_id' => $user->id,
            'personal_team' => false,
        ]);

        $this->seed(DemoWhatsAppConversationsSeeder::class);

        $this->assertGreaterThanOrEqual(
            15,
            Conversation::query()
                ->where('channel', 'whatsapp')
                ->where('message_sid', 'like', 'SM_DEMO_SEED_%')
                ->count(),
        );

        $team = Team::query()->where('name', 'Demo')->first();
        $this->assertNotNull($team);
        $team->refresh();
        $this->assertSame(
            DemoWhatsAppConversationsSeeder::DEMO_TEAM_WHATSAPP_LINE,
            preg_replace('/\D/', '', (string) $team->getWhatsAppFrom()),
        );
    }
}
