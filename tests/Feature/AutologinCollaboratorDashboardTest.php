<?php

namespace Tests\Feature;

use App\Helpers\TokenHelper;
use App\Http\Middleware\ModifyMenuBasedOnRole;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutologinCollaboratorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_autologin_sets_current_team_before_redirecting_collaborator(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->create(['current_team_id' => null]);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => false,
        ]);
        $user->teams()->attach($team->id, ['role' => 'editor']);
        $user->assignRole('collaborator');

        $this->assertNull($user->fresh()->current_team_id);

        $token = TokenHelper::generateSignedToken($user->fresh(), 'account_owner_autologin', 1);

        $response = $this->get(route('login.token', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertEquals($team->id, $user->fresh()->current_team_id);
        $this->assertNotNull(Auth::user()->currentTeam);
        $this->assertEquals($team->id, Auth::user()->currentTeam->id);
    }

    public function test_menu_middleware_refreshes_stale_null_current_team_relation(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->create(['current_team_id' => null]);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => false,
        ]);
        $user->teams()->attach($team->id, ['role' => 'editor']);
        $user->assignRole('collaborator');

        $this->actingAs($user->fresh());

        // Reproduce the bug: currentTeam relation cached as null before current_team_id is repaired.
        $authenticatedUser = Auth::user();
        $authenticatedUser->load(['currentTeam', 'roles', 'teams']);
        $this->assertNull($authenticatedUser->currentTeam);

        $middleware = app(ModifyMenuBasedOnRole::class);
        $request = Request::create('/dashboard/analytics', 'GET');
        $request->setUserResolver(fn () => Auth::user());

        $response = $middleware->handle($request, function () use ($team)
        {
            $this->assertNotNull(Auth::user()->currentTeam);
            $this->assertEquals($team->id, Auth::user()->currentTeam->id);
            $this->assertIsInt(Contact::getTotalCollaborators());

            return response('ok');
        });

        $this->assertSame('ok', $response->getContent());
        $this->assertEquals($team->id, $user->fresh()->current_team_id);
    }

    public function test_autologin_redirects_to_error_without_team_when_user_has_no_team(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->create(['current_team_id' => null]);
        $user->assignRole('collaborator');

        $token = TokenHelper::generateSignedToken($user->fresh(), 'account_owner_autologin', 1);

        $response = $this->get(route('login.token', ['token' => $token]));

        $response->assertRedirect(route('error-without-team'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->current_team_id);
    }

    public function test_menu_middleware_redirects_to_error_without_team_when_user_has_no_team(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->create(['current_team_id' => null]);
        $user->assignRole('collaborator');

        $this->actingAs($user->fresh());

        $middleware = app(ModifyMenuBasedOnRole::class);
        $request = Request::create('/dashboard/analytics', 'GET');
        $request->setUserResolver(fn () => Auth::user());
        $request->setRouteResolver(function () use ($request)
        {
            $route = new \Illuminate\Routing\Route(['GET'], '/dashboard/analytics', []);
            $route->name('dashboard');
            $route->bind($request);

            return $route;
        });

        $response = $middleware->handle($request, function ()
        {
            return response('should-not-reach');
        });

        $this->assertTrue($response->isRedirect());
        $this->assertSame(route('error-without-team'), $response->headers->get('Location'));
    }

    public function test_autologin_uses_intended_url_when_present(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->create(['current_team_id' => null]);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => false,
        ]);
        $user->teams()->attach($team->id, ['role' => 'editor']);
        $user->assignRole('collaborator');

        $token = TokenHelper::generateSignedToken($user->fresh(), 'account_owner_autologin', 1);
        $intended = route('dashboard.collaborator');

        $response = $this->withSession(['url.intended' => $intended])
            ->get(route('login.token', ['token' => $token]));

        $response->assertRedirect($intended);
    }

    public function test_autologin_falls_back_to_analytics_without_intended_url(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->create(['current_team_id' => null]);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => false,
        ]);
        $user->teams()->attach($team->id, ['role' => 'editor']);
        $user->assignRole('collaborator');

        $token = TokenHelper::generateSignedToken($user->fresh(), 'account_owner_autologin', 1);

        $response = $this->get(route('login.token', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));
    }
}
