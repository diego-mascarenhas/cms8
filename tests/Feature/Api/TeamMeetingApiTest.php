<?php

namespace Tests\Feature\Api;

use App\Enums\MeetingTranscriptStatus;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\MeetingTranscript;
use App\Models\Module;
use App\Models\User;
use App\Services\MeetingSummaryService;
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
        Module::query()->updateOrCreate(
            ['key' => 'meetings'],
            [
                'name' => 'Meetings',
                'icon' => 'microphone',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->enableModule('meetings');
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
        Queue::assertPushedOn('meetings', TranscribeMeetingTranscript::class);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/team/meetings/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonMissingPath('data.consumption')
            ->assertJsonMissingPath('data.summary.consumption');

        $this->flushHeaders()
            ->getJson('/api/team/meetings/'.$id)
            ->assertUnauthorized();
    }

    public function test_team_token_can_ask_again_for_the_summary(): void
    {
        Module::query()->updateOrCreate(
            ['key' => 'meetings'],
            [
                'name' => 'Meetings',
                'icon' => 'microphone',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->enableModule('meetings');
        $token = $team->createApiToken('Jitsi')['plain'];
        $meeting = MeetingTranscript::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'iniciativa: Primera semana visible',
            'external_ref' => 'colab:proposal:12',
            'original_filename' => 'reunion.m4a',
            'audio_path' => 'meetings/'.$team->id.'/reunion.m4a',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Pending,
            'turns' => [
                ['speaker' => 'speaker_0', 'label' => 'Persona 1', 'text' => 'Arrancamos con las pausas activas.'],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/team/meetings/'.$meeting->id.'/summary')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La reunión todavía no tiene una transcripción para resumir.');

        $meeting->forceFill(['status' => MeetingTranscriptStatus::Ready])->save();
        $this->mock(MeetingSummaryService::class)
            ->shouldReceive('summarize')
            ->once()
            ->withArgs(function (MeetingTranscript $row, bool $createTasks) use ($meeting): bool
            {
                return $row->id === $meeting->id && $createTasks === false;
            })
            ->andReturnUsing(function (MeetingTranscript $row): array
            {
                $summary = ['minutes' => 'Resumen nuevo de las pausas activas.'];
                $row->forceFill(['summary' => $summary])->save();

                return $summary;
            });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/team/meetings/'.$meeting->id.'/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.minutes', 'Resumen nuevo de las pausas activas.');
    }
}
