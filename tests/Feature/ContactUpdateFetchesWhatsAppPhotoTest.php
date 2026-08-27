<?php

namespace Tests\Feature;

use App\Jobs\FetchWhatsAppProfilePhotoJob;
use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactUpdateFetchesWhatsAppPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_contact_fetches_whatsapp_profile_photo(): void
    {
        Bus::fake();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('contacts');

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Diego',
            'phone' => '34600111222',
            'language' => 'es',
            'status_id' => 1,
        ]);

        $this->actingAs($user)
            ->put(route('contact.update', $contact->id), [
                'name' => 'Diego',
                'surname' => 'Mascarenhas',
                'email' => 'diego@example.com',
                'phone' => '34600111222',
                'birthday' => '',
                'status_id' => 1,
                'country' => '724',
                'language' => 'es',
                'responsible_id' => $user->id,
            ])
            ->assertRedirect(route('contact.show', $contact->id));

        Bus::assertDispatched(FetchWhatsAppProfilePhotoJob::class, function (FetchWhatsAppProfilePhotoJob $job) use ($team): bool
        {
            return $job->teamId === (int) $team->id && $job->phone === '34600111222';
        });
    }
}
