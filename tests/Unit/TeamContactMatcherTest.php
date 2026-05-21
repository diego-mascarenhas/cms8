<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\Contacts\TeamContactMatcher;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamContactMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
    }

    public function test_find_existing_matches_email_phone_and_split_full_name(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'name' => 'Francisco',
            'surname' => 'Caballero',
            'email' => 'francisco@example.com',
            'phone' => 5491112345678,
        ]);

        $matcher = app(TeamContactMatcher::class);

        $byFullName = $matcher->findExisting($team->id, null, null, 'Francisco Caballero');
        $this->assertNotNull($byFullName);
        $this->assertSame('Francisco', $byFullName->name);

        $byEmail = $matcher->findExisting($team->id, 'francisco@example.com', null, 'Other Name');
        $this->assertNotNull($byEmail);

        $byPhone = $matcher->findExisting($team->id, null, 5491112345678, 'Unknown');
        $this->assertNotNull($byPhone);

        $missing = $matcher->findExisting($team->id, null, null, 'María López');
        $this->assertNull($missing);
    }

    public function test_split_full_name_separates_first_and_surname(): void
    {
        $matcher = app(TeamContactMatcher::class);

        $this->assertSame(['Ana', 'García'], $matcher->splitFullName('Ana García'));
        $this->assertSame(['Pedro', null], $matcher->splitFullName('Pedro'));
    }

    public function test_search_finds_contact_by_partial_name(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'name' => 'Francisco',
            'surname' => 'Caballero',
        ]);

        $matcher = app(TeamContactMatcher::class);

        $this->assertCount(1, $matcher->search($team->id, 'Francisco Caballero'));
        $this->assertSame([$matcher->search($team->id, 'Francisco Caballero')->first()->id], $matcher->findIdsByName($team->id, 'Francisco Caballero'));
    }

    public function test_search_matches_partial_phone_digits_without_sql_error(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'name' => 'Pepe',
            'phone' => 549112345678,
        ]);

        $matcher = app(TeamContactMatcher::class);

        $results = $matcher->search($team->id, '123456');

        $this->assertCount(1, $results);
        $this->assertSame('Pepe', $results->first()->name);
    }

    public function test_find_existing_by_single_name_does_not_throw_on_postgresql_style_sql(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $pepe = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'name' => 'Pepe',
            'surname' => null,
        ]);

        $matcher = app(TeamContactMatcher::class);

        $byName = $matcher->findExisting($team->id, null, null, 'Pepe');
        $this->assertNotNull($byName);
        $this->assertSame($pepe->id, $byName->id);

        $ids = $matcher->findIdsByName($team->id, 'Pepe');
        $this->assertSame([$pepe->id], $ids);
    }
}
