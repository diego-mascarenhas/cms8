<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageStoreSaveIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
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
    private function baseStorePayload(): array
    {
        return [
            'id' => '',
            'name' => 'Save intent message',
            'text' => 'Alternative text for message body',
            'type_id' => 1,
            'message_category_ids' => [],
            'contact_status_id' => '',
            'min_hours_between_emails' => 48,
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
        ];
    }

    public function test_message_store_save_schedule_redirects_to_edit_with_flash(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user)->post(route('message.store'), $this->baseStorePayload());
        $message = Message::withoutGlobalScopes()
            ->where('team_id', $user->current_team_id)
            ->where('name', 'Save intent message')
            ->first();
        $this->assertNotNull($message);

        $scheduledLocal = now()->timezone(config('app.timezone'))->addDays(2)->startOfMinute();
        $schedulePayload = $scheduledLocal->format('Y-m-d\TH:i');

        $response = $this->actingAs($user)->post(route('message.store'), array_merge($this->baseStorePayload(), [
            'id' => (string) $message->id,
            'save_intent' => 'save_schedule',
            'schedule_send_at' => $schedulePayload,
        ]));
        $response->assertRedirect(route('message.index'));
        $dtLabel = $scheduledLocal->clone()->locale(app()->getLocale())->translatedFormat('d M Y H:i');
        $response->assertSessionHas('success', __('app.message_save_schedule_success', ['datetime' => $dtLabel]));

        $message->refresh();
        $this->assertNotNull($message->scheduled_send_at);
        $this->assertTrue($message->scheduled_send_at->greaterThan(now()));
    }

    public function test_message_store_save_send_redirects_to_edit_when_sender_not_set(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user)->post(route('message.store'), $this->baseStorePayload());
        $message = Message::withoutGlobalScopes()
            ->where('team_id', $user->current_team_id)
            ->where('name', 'Save intent message')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('message.store'), array_merge($this->baseStorePayload(), [
            'id' => (string) $message->id,
            'save_intent' => 'save_send',
        ]));
        $response->assertRedirect(route('message.edit', $message->id));
        $response->assertSessionHasErrors('save_intent');
    }

    public function test_message_store_save_send_activates_and_redirects_to_show_when_sender_configured(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $team = $user->currentTeam;
        $this->assertNotNull($team);
        $team->setSetting('mail_from_name', 'Test Sender');
        $team->setSetting('mail_from_address', 'sender@example.test');

        $this->actingAs($user)->post(route('message.store'), $this->baseStorePayload());
        $message = Message::withoutGlobalScopes()
            ->where('team_id', $user->current_team_id)
            ->where('name', 'Save intent message')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('message.store'), array_merge($this->baseStorePayload(), [
            'id' => (string) $message->id,
            'save_intent' => 'save_send',
        ]));

        $response->assertRedirect(route('message.show', $message->id));
        $response->assertSessionHas('success', __('app.message_save_send_success'));
        $this->assertSame(1, (int) Message::withoutGlobalScopes()->whereKey($message->id)->value('status_id'));
    }

    public function test_message_edit_renders_header_save_split_group(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user)->post(route('message.store'), $this->baseStorePayload());
        $message = Message::withoutGlobalScopes()
            ->where('team_id', $user->current_team_id)
            ->where('name', 'Save intent message')
            ->firstOrFail();

        $response = $this->actingAs($user)->get(route('message.edit', $message->id));
        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('form="message-store-form"', $html);
        $this->assertStringContainsString('name="save_intent"', $html);
        $this->assertStringContainsString('value="save_send"', $html);
        $this->assertStringContainsString('value="save_schedule"', $html);
        $this->assertStringContainsString('id="message-schedule-at-input"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('data-min-datetime="', $html);
    }

    public function test_message_edit_schedule_modal_loads_flatpickr_spanish_locale_when_app_locale_is_spanish(): void
    {
        $previousLocale = app()->getLocale();
        try
        {
            app()->setLocale('es');
            $user = $this->userWithPersonalTeamResolved();
            $this->actingAs($user)->post(route('message.store'), $this->baseStorePayload());
            $message = Message::withoutGlobalScopes()
                ->where('team_id', $user->current_team_id)
                ->where('name', 'Save intent message')
                ->firstOrFail();

            $response = $this->actingAs($user)->get(route('message.edit', $message->id));
            $response->assertOk();
            $html = $response->getContent() ?? '';
            $this->assertStringContainsString('dist/l10n/es.js', $html);
            $this->assertStringContainsString('data-fp-locale="es"', $html);
        } finally
        {
            app()->setLocale($previousLocale);
        }
    }
}
