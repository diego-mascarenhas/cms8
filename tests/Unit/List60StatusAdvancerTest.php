<?php

namespace Tests\Unit;

use App\Models\List60Status;
use App\Support\List60StatusAdvancer;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class List60StatusAdvancerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(List60StatusesSeeder::class);
    }

    public function test_initial_status_creates_missing_sin_contactar(): void
    {
        List60Status::query()->where('name', 'Sin contactar')->delete();

        $id = List60StatusAdvancer::initialStatusId();

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('list60_statuses', ['id' => $id, 'name' => 'Sin contactar']);
    }

    public function test_initial_status_is_sin_contactar(): void
    {
        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();

        $this->assertSame($sinContactar->id, List60StatusAdvancer::initialStatusId());
    }

    public function test_first_outreach_sets_one_contact_status(): void
    {
        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();
        $oneContact = List60Status::query()->where('name', '1 Contacto')->firstOrFail();

        $this->assertSame($oneContact->id, List60StatusAdvancer::statusIdAfterOutreach($sinContactar->id));
    }

    public function test_outreach_advances_through_contact_count_statuses(): void
    {
        $oneContact = List60Status::query()->where('name', '1 Contacto')->firstOrFail();
        $twoContacts = List60Status::query()->where('name', '2 Contactos')->firstOrFail();
        $threeContacts = List60Status::query()->where('name', '3 Contactos')->firstOrFail();

        $this->assertSame($twoContacts->id, List60StatusAdvancer::statusIdAfterOutreach($oneContact->id));
        $this->assertSame($threeContacts->id, List60StatusAdvancer::statusIdAfterOutreach($twoContacts->id));
        $this->assertSame($threeContacts->id, List60StatusAdvancer::statusIdAfterOutreach($threeContacts->id));
    }
}
