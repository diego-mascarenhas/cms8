<?php

namespace Tests\Feature;

use App\Mail\TestMessageMail;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessageTestSendFromTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_test_send_from_template_succeeds_for_team_template(): void
    {
        Mail::fake();

        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl for test send',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Hi {{name}}</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('message.test-from-template'), [
            'template_id' => $template->id,
            'draft_name' => 'Draft broadcast name',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'email' => $user->email,
            'emails' => [$user->email],
        ]);

        Mail::assertSent(TestMessageMail::class, function (TestMessageMail $mail) use ($user): bool
        {
            return $mail->hasTo($user->email);
        });
    }

    public function test_test_send_from_template_sends_to_multiple_recipients(): void
    {
        Mail::fake();

        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl multi send',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Hi</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('message.test-from-template'), [
            'template_id' => $template->id,
            'test_recipients' => 'one@example.com, two@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'email' => 'one@example.com, two@example.com',
            'emails' => ['one@example.com', 'two@example.com'],
        ]);

        $this->assertCount(2, Mail::sent(TestMessageMail::class));
        Mail::assertSent(TestMessageMail::class, function (TestMessageMail $mail): bool
        {
            return $mail->hasTo('one@example.com');
        });
        Mail::assertSent(TestMessageMail::class, function (TestMessageMail $mail): bool
        {
            return $mail->hasTo('two@example.com');
        });
    }

    public function test_test_send_from_template_rejects_invalid_recipient_email(): void
    {
        Mail::fake();

        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl invalid rcpt',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<p>x</p>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('message.test-from-template'), [
            'template_id' => $template->id,
            'test_recipients' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['test_recipients']);

        Mail::assertNothingSent();
    }

    public function test_test_send_from_template_rejects_foreign_team_template_id(): void
    {
        Mail::fake();

        $userA = $this->userWithPersonalTeamResolved();
        $teamAId = (int) $userA->current_team_id;

        $userB = $this->userWithPersonalTeamResolved();
        $this->assertNotSame($teamAId, (int) $userB->current_team_id);

        $templateA = Template::withoutGlobalScopes()->create([
            'name' => 'Team A only',
            'team_id' => $teamAId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<p>x</p>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($userB)->postJson(route('message.test-from-template'), [
            'template_id' => $templateA->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['template_id']);

        Mail::assertNothingSent();
    }

    public function test_saved_message_test_send_accepts_json_test_recipients(): void
    {
        Mail::fake();

        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl saved message test',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Hi</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Saved broadcast',
            'type_id' => 1,
            'text' => 'Body',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('message.test', $message->id), [
            'test_recipients' => 'one@example.com, two@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'emails' => ['one@example.com', 'two@example.com'],
        ]);

        $this->assertCount(2, Mail::sent(TestMessageMail::class));
    }
}
