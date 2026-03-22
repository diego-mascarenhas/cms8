<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\UserResolverService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResolverWhatsAppContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);

        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }

        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    }

    public function test_link_phone_creates_contact_with_team_owner_as_creator(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $team->id, '+34 722 372 858');

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', '34722372858')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame($owner->id, (int) $contact->creator_id);
        $this->assertNotNull($contact->user_id);
    }

    public function test_resolve_user_matches_contacts_phone_and_links_user_when_user_id_null(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'name' => 'CRM Contact',
            'phone' => 34600222004,
            'status_id' => 1,
        ]);

        $service = app(UserResolverService::class);
        $user = $service->resolveUserForConversation('34600222004', null);

        $this->assertNotNull($user);
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', 34600222004)
            ->first();
        $this->assertNotNull($contact);
        $this->assertSame($user->id, (int) $contact->user_id);
    }

    public function test_resolve_user_matches_contacts_phone_national_digits_against_international_input(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'name' => 'Local digits',
            'phone' => 600222004,
            'status_id' => 1,
        ]);

        $service = app(UserResolverService::class);
        $user = $service->resolveUserForConversation('+34 600 222 004', null);

        $this->assertNotNull($user);
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', 600222004)
            ->first();
        $this->assertNotNull($contact);
        $this->assertSame($user->id, (int) $contact->user_id);
    }
}
