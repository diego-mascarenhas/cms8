<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactMailComposeListUrlMailboxModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_compose_list_url_is_null_when_team_mailbox_module_disabled(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Module::query()->firstOrCreate(
            ['key' => 'mailbox'],
            [
                'name' => 'Mailbox',
                'icon' => 'mail',
                'description' => 'Team mailbox',
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'email' => 'client@example.com',
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        $this->assertNull($contact->fresh()->mailComposeListUrl());
    }

    public function test_mail_compose_list_url_is_non_null_when_team_mailbox_module_enabled(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Module::query()->firstOrCreate(
            ['key' => 'mailbox'],
            [
                'name' => 'Mailbox',
                'icon' => 'mail',
                'description' => 'Team mailbox',
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('mailbox');

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'email' => 'client@example.com',
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        $url = $contact->fresh()->mailComposeListUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('compose=1', $url);
    }
}
