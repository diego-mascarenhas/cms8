<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class ApiPerformanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_measures_every_get_and_leaves_no_token(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $created = $team->createApiToken('Performance');
        config(['services.landing_widget.team_token' => $created['plain']]);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '34600111222',
            'language' => 'es',
            'creator_id' => $user->id,
            'status_id' => (int) ContactStatus::query()->value('id'),
        ]);

        Http::fake(fn () => Http::response(['ok' => true], 200));

        $this->artisan('start', [
            '--api-performance' => true,
            '--api-team' => $team->id,
            '--api-user' => $user->id,
        ])->assertSuccessful();

        $recorded = Http::recorded();
        $methods = $recorded->map(fn (array $pair): string => $pair[0]->method())->unique()->values()->all();
        $urls = $recorded->map(fn (array $pair): string => $pair[0]->url());

        $this->assertSame(['GET'], $methods);
        $this->assertTrue($urls->contains(fn (string $url): bool => str_contains($url, '/api/contacts/'.$contact->id)));
        $this->assertTrue($urls->contains(fn (string $url): bool => str_contains($url, '/api/team/prompts')));
        $this->assertFalse($urls->contains(fn (string $url): bool => str_contains($url, '/api/embed/automation/')));

        $contactCall = $recorded->first(fn (array $pair): bool => str_contains($pair[0]->url(), '/api/contacts/'.$contact->id));
        $teamCall = $recorded->first(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/api/team/prompts'));

        $this->assertNotNull($contactCall);
        $this->assertNotNull($teamCall);
        $this->assertTrue($contactCall[0]->hasHeader('Authorization'));
        $this->assertFalse($contactCall[0]->hasHeader('Authorization', 'Bearer '.$created['plain']));
        $this->assertTrue($teamCall[0]->hasHeader('Authorization', 'Bearer '.$created['plain']));
        $this->assertSame(0, $user->tokens()->where('name', 'api-performance')->count());
    }

    public function test_start_can_measure_admin_instead_of_the_local_app(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $plain = $team->createApiToken('Performance')['plain'];
        config(['services.landing_widget.team_token' => $plain]);
        Http::fake(fn () => Http::response(['ok' => true], 200));

        $this->artisan('start', [
            '--api-performance' => true,
            '--api-team' => $team->id,
            '--api-host' => 'admin',
        ])->assertSuccessful();

        $userCall = Http::recorded()->first(fn (array $pair): bool => str_contains($pair[0]->url(), 'https://admin.idoneo.dev/api/user'));
        $teamCall = Http::recorded()->first(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/api/team/prompts'));

        $this->assertNotNull($userCall);
        $this->assertNotNull($teamCall);
        $this->assertTrue($userCall[0]->hasHeader('Authorization', 'Bearer '.$plain));
        $this->assertTrue($teamCall[0]->hasHeader('Authorization', 'Bearer '.$plain));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_remote_host_uses_tokens_issued_there(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        Http::fake(fn () => Http::response(['ok' => true], 200));

        $this->artisan('api:performance', [
            '--team' => $team->id,
            '--base-url' => 'https://staging.idoneo.dev',
            '--token' => 'sanctum-from-staging',
            '--team-token' => 'team-from-staging',
        ])->assertSuccessful();

        $contact = Http::recorded()->first(fn (array $pair): bool => str_contains($pair[0]->url(), 'https://staging.idoneo.dev/api/user'));
        $teamCall = Http::recorded()->first(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/api/team/prompts'));

        $this->assertNotNull($contact);
        $this->assertNotNull($teamCall);
        $this->assertTrue($contact[0]->hasHeader('Authorization', 'Bearer sanctum-from-staging'));
        $this->assertTrue($teamCall[0]->hasHeader('Authorization', 'Bearer team-from-staging'));
        $this->assertSame(0, $user->tokens()->count());
    }
}
