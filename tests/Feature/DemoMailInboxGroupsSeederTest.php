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

        $factura = $groups->first(fn (array $group): bool => str_contains((string) $group['subject'], 'Factura pendiente'));
        $this->assertNotNull($factura);
        $this->assertSame(2, $factura['count']);
        $reminder = $groups->first(fn (array $group): bool => $group['subject'] === 'Recordatorio factura IDONEO');
        $this->assertNotNull($reminder);
        $this->assertSame(1, $reminder['count']);
    }
}
