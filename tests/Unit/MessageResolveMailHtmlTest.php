<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageResolveMailHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_mail_html_prefers_message_column_over_template(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>From template</p>', 'css' => ''],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Msg',
            'type_id' => 1,
            'text' => 'Alt',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'mail_html' => '<p>From message</p>',
            'status_id' => false,
        ]);

        $message->load('template');

        $this->assertSame('<p>From message</p>', $message->resolveMailHtml());
    }
}
