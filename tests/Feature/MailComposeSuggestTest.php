<?php

namespace Tests\Feature;

use App\Mail\ComposeMessage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailComposeSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_suggest_returns_401_when_guest(): void
    {
        $response = $this->postJson(route('mail.compose-suggest'), [
            'hint' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_compose_suggest_returns_403_without_team(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('mail.compose-suggest'), [
            'hint' => 'Draft a follow-up',
        ]);

        $response->assertStatus(403);
    }

    public function test_compose_suggest_returns_body_when_authenticated_with_team(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        AnonymousAgent::fake(['{"subject":"Follow-up","body":"Suggested email body text."}']);

        $response = $this->actingAs($user)->postJson(route('mail.compose-suggest'), [
            'hint' => 'Write a short thank-you note.',
            'recipient_summary' => 'client@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'subject' => 'Follow-up',
            'body' => 'Suggested email body text.',
            'response' => 'Suggested email body text.',
        ]);
    }

    public function test_compose_suggest_falls_back_when_model_returns_plain_text(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        AnonymousAgent::fake(['Plain body only.']);

        $response = $this->actingAs($user)->postJson(route('mail.compose-suggest'), [
            'hint' => 'Hello',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'subject' => '',
            'body' => 'Plain body only.',
            'response' => 'Plain body only.',
        ]);
    }

    public function test_compose_send_delivers_cc_and_bcc(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Mail::fake();

        $response = $this->actingAs($user)->postJson(route('mail.compose-send'), [
            'to' => ['ana@example.com'],
            'cc' => ['copia@example.com'],
            'bcc' => ['oculta@example.com'],
            'subject' => 'Re: Consulta',
            'body' => "Te lo mando hoy.\n\n---------- Mensaje original ----------",
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        Mail::assertSent(ComposeMessage::class, function (ComposeMessage $mail): bool
        {
            return $mail->hasTo('ana@example.com')
                && $mail->hasCc('copia@example.com')
                && $mail->hasBcc('oculta@example.com')
                && $mail->envelope()->subject === 'Re: Consulta';
        });
    }
}
