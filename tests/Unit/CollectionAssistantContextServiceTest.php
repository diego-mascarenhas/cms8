<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\CollectionAssistantContextService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionAssistantContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_markdown_includes_contact_crm_fields(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Cobranza',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $md = app(CollectionAssistantContextService::class)->buildMarkdownForContact($contact->id, $team->id);

        $this->assertStringContainsString('Contexto del cliente', $md);
        $this->assertStringContainsString('Cliente Cobranza', $md);
        $this->assertStringContainsString((string) $contact->id, $md);
    }

    public function test_returns_empty_for_unknown_contact(): void
    {
        $md = app(CollectionAssistantContextService::class)->buildMarkdownForContact(999_999, 1);

        $this->assertSame('', $md);
    }
}
