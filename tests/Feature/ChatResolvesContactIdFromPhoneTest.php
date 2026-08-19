<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatResolvesContactIdFromPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_index_includes_contact_id_hidden_field_when_crm_matches_phone_without_user(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'phone' => '722372858',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('chat.index', ['phone' => '722372858']));

        $response->assertStatus(200);
        $response->assertSee('id="contact-id" value="'.$contact->id.'"', false);
    }

    public function test_chat_index_matches_spanish_international_prefix_to_contact_phone(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'phone' => '722372858',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('chat.index', ['phone' => '34722372858']));

        $response->assertStatus(200);
        $response->assertSee('id="contact-id" value="'.$contact->id.'"', false);
    }

    public function test_chat_index_returns_403_when_phone_is_blacklisted(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('assistant_whatsapp_blacklist_numbers', '34722372858');

        $response = $this->actingAs($user)->get(route('chat.index', ['phone' => '34722372858']));

        $response->assertDeniedForBrowser();
    }
}
