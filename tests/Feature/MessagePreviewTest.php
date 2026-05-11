<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePreviewTest extends TestCase
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

    public function test_message_preview_renders_iframe_with_template_html(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Preview tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<!DOCTYPE html><html><body><p>UNIQUE_BODY_MARK</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Mail preview',
            'type_id' => 1,
            'text' => 'Preheader',
            'team_id' => $teamId,
            'template_id' => $template->id,
        ]);

        $response = $this->actingAs($user)->get(route('message.preview', $message->id));

        $response->assertOk();
        $response->assertSee('preview-email-frame', false);
        $response->assertSee('message/'.$message->id.'/preview-html', false);

        $frame = $this->actingAs($user)->get(route('message.preview.html', $message->id));
        $frame->assertOk();
        $frame->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $frame->assertSee('UNIQUE_BODY_MARK', false);
    }
}
