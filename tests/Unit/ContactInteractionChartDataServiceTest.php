<?php

namespace Tests\Unit;

use App\Enums\ContactInteractionType;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\User;
use App\Services\ContactInteractionChartDataService;
use Carbon\Carbon;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactInteractionChartDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactInteractionChartDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
        $this->service = app(ContactInteractionChartDataService::class);
    }

    public function test_build_type_breakdown_groups_interactions_for_contact(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => ContactInteractionType::Call,
            'occurred_at' => Carbon::now()->subDays(2),
        ]);
        ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => ContactInteractionType::WhatsApp,
            'occurred_at' => Carbon::now()->subDay(),
        ]);
        ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => ContactInteractionType::WhatsApp,
            'occurred_at' => Carbon::now()->subDay(),
        ]);

        $data = $this->service->buildTypeBreakdown($team->id, $contact->id);

        $this->assertSame(3, $data['total']);
        $this->assertContains(__('contact_interaction_type.call'), $data['labels']);
        $this->assertContains(__('contact_interaction_type.whatsapp'), $data['labels']);
        $whatsappIndex = array_search(__('contact_interaction_type.whatsapp'), $data['labels'], true);
        $this->assertSame(2, $data['values'][$whatsappIndex]);
    }

    public function test_build_daily_trend_by_type_groups_counts_per_day(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        $day = Carbon::now()->subDay()->startOfDay();
        ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => ContactInteractionType::Call,
            'occurred_at' => $day->copy()->addHours(10),
        ]);
        ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => ContactInteractionType::WhatsApp,
            'occurred_at' => $day->copy()->addHours(14),
        ]);

        $data = $this->service->buildDailyTrendByType($team->id, $contact->id, days: 30);

        $this->assertSame(2, $data['total']);
        $this->assertCount(30, $data['labels']);
        $this->assertCount(2, $data['series']);
        $dayIndex = count($data['labels']) - 2;
        $callSeries = collect($data['series'])->firstWhere('name', __('contact_interaction_type.call'));
        $whatsappSeries = collect($data['series'])->firstWhere('name', __('contact_interaction_type.whatsapp'));
        $this->assertSame(1, $callSeries['data'][$dayIndex]);
        $this->assertSame(1, $whatsappSeries['data'][$dayIndex]);
    }

    public function test_build_type_breakdown_scopes_to_team(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $teamA = $userA->ownedTeams()->first();
        $userB = User::factory()->withPersonalTeam()->create();
        $teamB = $userB->ownedTeams()->first();

        $contactA = Contact::factory()->create([
            'team_id' => $teamA->id,
            'responsible_id' => $userA->id,
            'creator_id' => $userA->id,
        ]);
        $contactB = Contact::factory()->create([
            'team_id' => $teamB->id,
            'responsible_id' => $userB->id,
            'creator_id' => $userB->id,
        ]);

        ContactInteraction::factory()->create([
            'contact_id' => $contactA->id,
            'user_id' => $userA->id,
            'type' => ContactInteractionType::Email,
            'occurred_at' => Carbon::now(),
        ]);
        ContactInteraction::factory()->create([
            'contact_id' => $contactB->id,
            'user_id' => $userB->id,
            'type' => ContactInteractionType::Meeting,
            'occurred_at' => Carbon::now(),
        ]);

        $data = $this->service->buildTypeBreakdown($teamA->id);

        $this->assertSame(1, $data['total']);
        $this->assertSame([__('contact_interaction_type.email')], $data['labels']);
    }
}
