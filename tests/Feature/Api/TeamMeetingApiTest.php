<?php

namespace Tests\Feature\Api;

use App\Jobs\TranscribeMeetingTranscript;
use App\Models\MeetingTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamMeetingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_token_can_upload_and_read_a_meeting(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $token = $team->createApiToken('Jitsi')['plain'];

        $store = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('Accept', 'application/json')
            ->post('/api/team/meetings', [
                'title' => 'Sala 1234',
                'language' => 'es',
                'external_ref' => '1234',
                'audio' => UploadedFile::fake()->create('sala.mp3', 100, 'audio/mpeg'),
            ]);

        $store->assertCreated()
            ->assertJsonPath('data.title', 'Sala 1234')
            ->assertJsonPath('data.external_ref', '1234')
            ->assertJsonPath('data.status', 'pending');

        $id = $store->json('data.id');
        Storage::disk('local')->assertExists(MeetingTranscript::withoutGlobalScope('team')->find($id)->audio_path);
        Queue::assertPushed(TranscribeMeetingTranscript::class);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/team/meetings/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->flushHeaders()
            ->getJson('/api/team/meetings/'.$id)
            ->assertUnauthorized();
    }
}
