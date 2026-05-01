<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_campaign_show_renders_statistics_and_linked_messages(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Mailing linked',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
        ]);

        $messageB = Message::withoutGlobalScopes()->create([
            'name' => 'Segundo paso',
            'type_id' => 1,
            'text' => 'Hi 2',
            'team_id' => $teamId,
            'min_hours_between_emails' => 48,
        ]);

        $contactA = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $contactB = Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Show Stats Campaign',
        ]);

        $campaign->messages()->syncWithoutDetaching([
            $message->id => ['sort_order' => 0],
            $messageB->id => [
                'sort_order' => 1,
                'delay_minutes_after_previous' => 120,
                'conditions' => ['require_previous' => 'opened'],
            ],
        ]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contactA->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
            'sent_at' => now()->subHour(),
            'delivered_at' => now()->subHour(),
            'opened_at' => now()->subMinutes(5),
        ]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contactB->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
            'sent_at' => now()->subHour(),
            'delivered_at' => now()->subHour(),
            'clicked_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.show', $campaign));

        $response->assertOk();
        $response->assertViewHas('deliveryStats', function (array $stats): bool
        {
            return $stats['total'] === 2
                && $stats['unique_recipients'] === 2
                && $stats['sent'] === 2
                && $stats['delivered'] === 2
                && $stats['opened'] === 1
                && $stats['clicked'] === 1;
        });
        $response->assertSee('Show Stats Campaign', false);
        $response->assertSee('Creación', false);
        $response->assertSee('Actualización', false);
        $response->assertSee('Mailing linked', false);
        $response->assertSee('Segundo paso', false);
        $response->assertSee('Reglas de campaña para este paso', false);
        $response->assertSee('Espera tras el paso anterior', false);
        $response->assertSee(route('message.show', $message->id), false);
        $response->assertSee('Añadir mensaje', false);
        $response->assertSee(e(route('campaigns.templates.select', [
            'type' => $campaign->type,
            'title' => $campaign->name,
            'campaign_id' => $campaign->id,
        ])), false);
        $response->assertSee('Guardar cambios', false);
        $response->assertSee(route('campaigns.sequence.update', $campaign), false);
    }

    public function test_campaign_sequence_patch_from_show_updates_pivot(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Paso uno',
            'type_id' => 1,
            'text' => 'A',
            'team_id' => $teamId,
        ]);
        $messageB = Message::withoutGlobalScopes()->create([
            'name' => 'Paso dos',
            'type_id' => 1,
            'text' => 'B',
            'team_id' => $teamId,
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Patch sequence',
        ]);

        $campaign->messages()->syncWithoutDetaching([
            $message->id => ['sort_order' => 0, 'conditions' => null],
            $messageB->id => ['sort_order' => 1, 'conditions' => null],
        ]);

        $response = $this->actingAs($user)->patch(route('campaigns.sequence.update', $campaign), [
            'sequence' => [
                [
                    'message_id' => $message->id,
                    'sort_order' => 0,
                    'delay_minutes_after_previous' => '',
                    'condition_preset' => 'none',
                ],
                [
                    'message_id' => $messageB->id,
                    'sort_order' => 1,
                    'delay_minutes_after_previous' => 90,
                    'condition_preset' => 'clicked',
                ],
            ],
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $response->assertSessionHas('success');

        $row = DB::table('campaign_message')
            ->where('campaign_id', $campaign->id)
            ->where('message_id', $messageB->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->sort_order);
        $this->assertSame(90, (int) $row->delay_minutes_after_previous);
        $this->assertSame(['require_previous' => 'clicked'], json_decode((string) $row->conditions, true));
    }

    public function test_campaign_show_empty_sequence_shows_first_message_cta(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->sequenceSummary()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Empty sequence',
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.show', $campaign));

        $response->assertOk();
        $response->assertSee('Crear el primer mensaje', false);
        $response->assertSee('línea de tiempo', false);
        $response->assertSee('campaign_id='.$campaign->id, false);
    }
}
