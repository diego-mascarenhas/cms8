<?php

namespace Tests\Feature;

use App\Mail\NewUserNotification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewUserNotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_mailable_renders_short_body_and_cta(): void
    {
        $user = User::factory()->create([
            'name' => 'Pat Example',
        ]);

        $mailable = new NewUserNotification($user, null);
        $html = $mailable->render();

        $this->assertStringContainsString('Activar acceso', $html);
        $this->assertStringContainsString('¡Hola, Pat Example!', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net/gh/twitter/twemoji', $html);
    }

    public function test_welcome_email_hides_demo_prefixed_team_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Pat Example',
        ]);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Demo — ACME Workspace',
            'personal_team' => true,
        ]);

        $mailable = new NewUserNotification($user, $team);
        $html = $mailable->render();

        $this->assertStringNotContainsString('Demo —', (string) $mailable->subject);
        $this->assertStringNotContainsString('Demo —', $html);
        $this->assertStringContainsString('¡Hola, Pat Example!', $html);
        $this->assertStringNotContainsString('<strong>'.config('app.name').'</strong>', $html);
    }

    public function test_welcome_email_hides_team_named_demo_only(): void
    {
        $user = User::factory()->create(['name' => 'Pat Example']);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
            'personal_team' => true,
        ]);

        $mailable = new NewUserNotification($user, $team);
        $html = $mailable->render();

        $this->assertStringNotContainsString('<strong>Demo</strong>', $html);
        $this->assertStringNotContainsString('<strong>'.config('app.name').'</strong>', $html);
    }

    public function test_welcome_email_keeps_non_demo_team_starting_with_demo_letters(): void
    {
        $user = User::factory()->create(['name' => 'Pat Example']);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Democracia Sur',
            'personal_team' => true,
        ]);

        $mailable = new NewUserNotification($user, $team);
        $html = $mailable->render();

        $this->assertStringContainsString('<strong>Democracia Sur</strong>', $html);
    }

    public function test_welcome_email_has_no_footer_app_name_block(): void
    {
        $user = User::factory()->create(['name' => 'Pat Example']);

        $html = (new NewUserNotification($user, null))->render();

        $this->assertStringNotContainsString('class="foot"', $html);
    }
}
