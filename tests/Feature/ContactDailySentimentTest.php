<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\User;
use App\Services\ContactDailySentimentService;
use App\Services\ContactSentimentAnalysisService;
use Carbon\Carbon;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactDailySentimentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ContactSentimentSeeder::class,
            ContactStatusSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
        ]);

        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }
    }

    private function createAdminWithContactsModule(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Team contacts',
                'status' => 1,
            ],
        );
        $team->enableModule('contacts');

        return [$user, $team];
    }

    private function createContact(array $attributes, User $user, $team): Contact
    {
        return Contact::withoutGlobalScopes()->create(array_merge([
            'team_id' => $team->id,
            'creator_id' => $team->user_id,
            'responsible_id' => $team->user_id,
            'name' => 'Test Contact',
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ], $attributes));
    }

    public function test_dashboard_shows_current_contact_sentiment_counts(): void
    {
        [$user, $team] = $this->createAdminWithContactsModule();

        $contact = $this->createContact([
            'name' => 'Happy Contact',
        ], $user, $team);

        ContactSentimentHistory::create([
            'contact_id' => $contact->id,
            'sentiment_id' => 5,
            'notes' => 'Very positive sentiment',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Balance emocional', false);
        $response->assertSee('<span class="sentiment-count">1</span>', false);
    }

    public function test_compute_daily_command_runs_without_snapshot_table(): void
    {
        [$user, $team] = $this->createAdminWithContactsModule();

        $contact = $this->createContact([
            'name' => 'Daily Contact',
        ], $user, $team);

        ContactSentimentHistory::create([
            'contact_id' => $contact->id,
            'sentiment_id' => 4,
            'notes' => 'Existing positive sentiment',
        ]);

        $this->artisan('sentiment:compute-daily', [
            '--team' => (string) $team->id,
        ])->assertSuccessful();

        $data = app(ContactDailySentimentService::class)->chartDataForTeam($team);

        $this->assertSame(1, collect($data)->firstWhere('label', 'Positivo')['count']);
    }

    public function test_chart_data_reflects_current_sentiment_per_contact(): void
    {
        [$user, $team] = $this->createAdminWithContactsModule();

        $contact = $this->createContact([
            'name' => 'Negative Contact',
        ], $user, $team);

        ContactSentimentHistory::create([
            'contact_id' => $contact->id,
            'sentiment_id' => 2,
            'notes' => 'Negative sentiment',
        ]);

        $data = app(ContactDailySentimentService::class)->chartDataForTeam($team);

        $this->assertSame([
            ['label' => 'Muy Negativo', 'count' => 0],
            ['label' => 'Negativo', 'count' => 1],
            ['label' => 'Neutral', 'count' => 0],
            ['label' => 'Positivo', 'count' => 0],
            ['label' => 'Muy Positivo', 'count' => 0],
        ], $data);
    }

    public function test_daily_processing_passes_full_24h_context_not_only_last_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16 06:30:00'));

        [$user, $team] = $this->createAdminWithContactsModule();

        $contact = $this->createContact([
            'name' => 'WhatsApp Contact',
            'phone' => '34600111222',
        ], $user, $team);

        Module::query()->firstOrCreate(
            ['key' => 'chat'],
            [
                'name' => 'Chat',
                'icon' => 'message',
                'description' => 'Team chat',
                'status' => 1,
            ],
        );
        $team->enableModule('chat');
        $team->setSetting('whatsapp_from', '34999111222');
        $team = $team->fresh();

        Conversation::query()->create([
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999111222',
            'body' => 'Estoy muy enfadado con el retraso',
            'status' => 'received',
            'direction' => 'inbound',
            'created_at' => Carbon::parse('2026-06-15 10:00:00'),
            'updated_at' => Carbon::parse('2026-06-15 10:00:00'),
        ]);

        Conversation::query()->create([
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999111222',
            'body' => 'Gracias, quedó resuelto perfecto',
            'status' => 'received',
            'direction' => 'inbound',
            'created_at' => Carbon::parse('2026-06-15 18:00:00'),
            'updated_at' => Carbon::parse('2026-06-15 18:00:00'),
        ]);

        $this->mock(ContactSentimentAnalysisService::class, function ($mock) use ($contact): void
        {
            $mock->shouldReceive('recordForContact')
                ->once()
                ->with(
                    \Mockery::on(fn ($model) => $model instanceof Contact && (int) $model->id === (int) $contact->id),
                    \Mockery::on(function (string $context): bool
                    {
                        return str_contains($context, 'Estoy muy enfadado con el retraso')
                            && str_contains($context, 'Gracias, quedó resuelto perfecto')
                            && substr_count($context, '[WhatsApp') === 2;
                    }),
                    'daily',
                );
        });

        app(ContactDailySentimentService::class)->processTeam($team, Carbon::parse('2026-06-15 06:30:00'));

        Carbon::setTestNow();
    }
}
