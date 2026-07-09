<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Country;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCountryRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
    }

    /**
     * `country` is both a DB column (Country id) and a relation. Accessing the
     * attribute returns the raw int, so code must resolve the related model via
     * the relation. This guards against "Attempt to read property code on int".
     */
    public function test_country_attribute_is_int_but_relation_resolves_model(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $country = Country::query()->whereNotNull('code')->firstOrFail();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'country' => $country->id,
        ]);

        // Raw attribute is the integer id (the source of the historical crash).
        $this->assertIsInt($contact->country);

        // The relation resolves the Country model, so reading ->code is safe.
        $resolved = $contact->country()->first();
        $this->assertInstanceOf(Country::class, $resolved);
        $this->assertSame($country->code, $resolved->code);

        $contactCountryCode = $resolved?->code ? strtolower((string) $resolved->code) : null;
        $this->assertSame(strtolower((string) $country->code), $contactCountryCode);
    }
}
