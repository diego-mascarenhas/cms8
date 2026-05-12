<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageDataTableAjaxNoLazyDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            SourceSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTablesQueryParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'category_info', 'name' => 'category_info', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'progress', 'name' => 'progress', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'status_id', 'name' => 'status_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ];
    }

    #[Test]
    public function message_datatable_ajax_does_not_lazy_load_deliveries_per_row(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user);

        $teamId = (int) $user->current_team_id;
        $campaign = Campaign::factory()->create(['team_id' => $teamId]);

        for ($i = 0; $i < 5; $i++)
        {
            $message = Message::create([
                'name' => 'Campaign '.$i,
                'type_id' => 1,
                'text' => 'Hi',
                'team_id' => $teamId,
            ]);

            $contact = \App\Models\Contact::factory()->create([
                'team_id' => $teamId,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
            ]);

            MessageDelivery::create([
                'team_id' => $teamId,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'campaign_id' => $campaign->id,
                'status_id' => 1,
                'sent_at' => now(),
            ]);
        }

        $lazyDeliverySelects = 0;

        DB::listen(function ($query) use (&$lazyDeliverySelects): void
        {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'message_deliveries'))
            {
                return;
            }

            if (preg_match('/message_id["`]?\\s*=\\s*\\?/i', $query->sql))
            {
                $lazyDeliverySelects++;
            }
        });

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->getJson(route('message.index', $this->dataTablesQueryParams()));

        $response->assertOk();
        $response->assertJsonPath('recordsTotal', 5);

        $this->assertLessThanOrEqual(
            1,
            $lazyDeliverySelects,
            'Expected at most one per-row deliveries query (eager load); got '.$lazyDeliverySelects,
        );
    }
}
