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
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
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
        $this->assertNull($contact->user_id);
        $this->assertDatabaseMissing('users', [
            'email' => 'wa-34722372858@chat.placeholder',
        ]);
    }

    public function test_resolve_user_returns_null_for_contact_without_linked_user(): void
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
        $user = $service->resolveUserForConversation('34600222004', null, (int) $team->id);

        $this->assertNull($user);
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', 34600222004)
            ->first();
        $this->assertNotNull($contact);
        $this->assertNull($contact->user_id);
    }

    public function test_resolve_user_returns_null_for_national_phone_when_contact_has_no_user(): void
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
        $user = $service->resolveUserForConversation('+34 600 222 004', null, (int) $team->id);

        $this->assertNull($user);
    }

    public function test_resolve_user_prefers_team_staff_account_when_team_id_given(): void
    {
        $admin = User::factory()->create(['phone' => 5491136626495]);
        $admin->assignRole('admin');
        $team = Team::factory()->create(['user_id' => $admin->id]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);

        $placeholder = User::factory()->create([
            'email' => 'wa-5491136626495@chat.placeholder',
            'phone' => 5491136626495,
        ]);
        $placeholder->assignRole('client');

        $service = app(UserResolverService::class);
        $resolved = $service->resolveUserForConversation('5491136626495', null, (int) $team->id);

        $this->assertNotNull($resolved);
        $this->assertSame($admin->id, $resolved->id);
        $this->assertNotSame($placeholder->id, $resolved->id);
    }

    public function test_link_phone_does_not_replace_staff_user_on_existing_contact(): void
    {
        $admin = User::factory()->create(['phone' => 5491199988877]);
        $admin->assignRole('admin');
        $team = Team::factory()->create(['user_id' => $admin->id]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'name' => 'Diego Admin',
            'phone' => 5491199988877,
            'status_id' => 1,
        ]);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $team->id, '5491199988877', 'Diego');

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', 5491199988877)
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame($admin->id, (int) $contact->user_id);

        $resolved = $service->resolveUserForConversation('5491199988877', null, (int) $team->id);
        $this->assertSame($admin->id, $resolved->id);
    }

    public function test_resolve_user_prefers_staff_via_team_contact_email_when_phone_matches(): void
    {
        $admin = User::factory()->create([
            'email' => 'victor@machbel.com',
            'phone' => null,
        ]);
        $admin->assignRole('admin');
        $team = Team::factory()->create(['user_id' => $admin->id]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'name' => 'Victor Machbel',
            'email' => 'victor@machbel.com',
            'phone' => 34665086080,
            'status_id' => 1,
        ]);

        User::factory()->create([
            'email' => 'wa-34665086080@chat.placeholder',
            'phone' => 34665086080,
        ])->assignRole('client');

        $service = app(UserResolverService::class);
        $resolved = $service->resolveUserForConversation('34665086080', null, (int) $team->id);

        $this->assertNotNull($resolved);
        $this->assertSame($admin->id, $resolved->id);
    }

    public function test_link_phone_upgrades_global_admin_from_client_pivot_to_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'phone' => 34611111111,
        ]);
        $admin->assignRole('admin');
        $team = Team::factory()->create(['user_id' => $admin->id]);
        $admin->teams()->attach($team->id, ['role' => 'client']);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $team->id, '34611111111');

        $role = $admin->fresh()->teams()->where('team_id', $team->id)->value('team_user.role');
        $this->assertSame('admin', $role);
    }

    public function test_link_phone_does_not_add_foreign_team_admin_as_member(): void
    {
        $foreignAdmin = User::factory()->create([
            'email' => 'leotolosaperez@gmail.com',
            'phone' => 34600111222,
        ]);
        $foreignAdmin->assignRole('admin');
        $foreignTeam = Team::factory()->create(['user_id' => $foreignAdmin->id]);
        $foreignAdmin->teams()->attach($foreignTeam->id, ['role' => 'admin']);

        $hostOwner = User::factory()->create();
        $hostTeam = Team::factory()->create(['user_id' => $hostOwner->id]);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $hostTeam->id, '34600111222', 'Leonardo Tolosa Perez');

        $this->assertFalse(
            $foreignAdmin->fresh()->teams()->where('team_id', $hostTeam->id)->exists(),
            'A user who is admin on another team must not be auto-added to this team.',
        );

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $hostTeam->id)
            ->where('phone', 34600111222)
            ->first();

        $this->assertNotNull($contact);
        $this->assertNull($contact->user_id);
        $this->assertSame('Leonardo Tolosa Perez', $contact->name);
    }

    public function test_link_phone_adds_platform_root_user_to_team_as_admin(): void
    {
        $rootUser = User::factory()->create([
            'email' => 'root@humano.test',
            'phone' => 34600333444,
        ]);
        $rootUser->assignRole('root');

        $hostOwner = User::factory()->create();
        $hostTeam = Team::factory()->create(['user_id' => $hostOwner->id]);

        $service = app(UserResolverService::class);
        $service->linkPhoneToContactInTeam((int) $hostTeam->id, '34600333444');

        $role = $rootUser->fresh()->teams()->where('team_id', $hostTeam->id)->value('team_user.role');
        $this->assertSame('admin', $role);
    }
}
