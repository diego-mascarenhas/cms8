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

    public function test_link_phone_applies_profile_name_for_new_user_and_contact(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $team->id, '+34 611 222 333', 'Cliente Demo');

        $user = User::withoutGlobalScopes()->where('email', 'wa-34611222333@chat.placeholder')->first();
        $this->assertNotNull($user);
        $this->assertSame('Cliente Demo', $user->name);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', '34611222333')
            ->first();
        $this->assertNotNull($contact);
        $this->assertSame('Cliente Demo', $contact->name);
    }

    public function test_link_phone_updates_placeholder_user_when_profile_name_arrives_later(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $team->id, '+34 600 111 222');
        $service->linkPhoneToContactInTeam((int) $team->id, '+34 600 111 222', 'Real Name');

        $user = User::withoutGlobalScopes()->where('email', 'wa-34600111222@chat.placeholder')->first();
        $this->assertNotNull($user);
        $this->assertSame('Real Name', $user->name);
    }
}
